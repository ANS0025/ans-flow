<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use ANS_CLI\Console\FlowCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

class ReleaseCommand extends FlowCommand
{
    private string $usage = "usage: ans flow:release [list] [-v]\n" .
                     "       ans flow:release start <version>\n" .
                     "       ans flow:release finish [-pk] <version>"
                     ;

    /**
     * configure
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('flow:release')
            ->setDescription('Manage release branches')
            ->addArgument('action', InputArgument::OPTIONAL, 'The action to perform (start, finish, list)', 'list')
            ->addArgument('name', InputArgument::OPTIONAL, 'The release branch name')
            ->addOption('keep', 'k', InputOption::VALUE_NONE, 'Keep the release branch after finishing')
            ->addOption('push', 'p', InputOption::VALUE_NONE, 'Push the release branch to origin after finishing')
            ;
    }

    /**
     * execute
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->requireGitFlowInitialized($output);

        $action = $input->getArgument('action');
        $name = $input->getArgument('name');
        $verbose = $input->getOption('verbose');
        $keep = $input->getOption('keep');
        $push = $input->getOption('push');

        switch ($action) {
            case 'list':
                $this->listReleases($output, $verbose);
                break;
            case 'start':
                $this->startRelease($name, $output);
                break;
            case 'finish':
                $this->finishRelease($name, $keep, $push, $input, $output);
                break;
            default:
                $output->writeln(["Unknown subcommand: '$action'", $this->usage]);

                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Start a new release branch
     *
     * @param string          $name
     * @param OutputInterface $output
     */
    protected function startRelease(?string $name, OutputInterface $output): void
    {
        $productionBranch = 'production';
        $releasePrefix = $this->getPrefix('release', $output);
        $releaseBranch = $releasePrefix . $name;

        // Sanity checks
        $this->branchNameExists($name, $this->usage, $output);
        $this->requireRleaseBranchesAbsent($output);
        $this->requireNoExistingTags($name, $output);

        // Create the release branch based on the production branch
        $this->createBranch($releaseBranch, $productionBranch, $output);

        // Output the summary
        $output->writeln([
            "Switched to a new branch '{$releaseBranch}'",
            "",
            "Summary of actions:",
            "- A new branch '{$releaseBranch}' was created, based on '{$productionBranch}'",
            "- You are now on branch '{$releaseBranch}'",
            "",
            "Follow-up actions:",
            "- Bump the version number now!",
            "- Start committing last-minute fixes in preparing your release",
            "When done, run:",
            "",
            "    ans flow:release finish '{$name}'",
            "",
        ]);
    }

    /**
     * Finish a release branch
     *
     * @param string          $name
     * @param bool            $keep
     * @param bool            $push
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function finishRelease(?string $name, bool $keep, bool $push, InputInterface $input, OutputInterface $output): void
    {
        $productionBranch = 'production';
        $releasePrefix = $this->getPrefix('release', $output);
        $releaseBranch = $releasePrefix . $name;

        // Sanity checks
        $this->branchNameExists($name, $this->usage, $output);
        $this->requireReleaseBranch($releaseBranch, $output);
        $this->requireCleanWorkingTree($output);

        // Fetch changes from origin
        $this->fetchBranch($productionBranch, $output);

        // Ask for the tag message
        $tagName = str_replace($releasePrefix, '', $name);
        if (!$this->tagExists($name)) {
            $tagMessage = $this->askForTagMessage($input, $output);
        }

        // Merge release branch into production branch
        $this->mergeBranch($releaseBranch, $productionBranch, $output);

        // Create a new tag
        if (!$this->tagExists($tagName)) {
            $this->createTag($tagName, $tagMessage, $output);
        }

        // Push branches and tags to origin
        if ($push) {
            $this->pushBranch($productionBranch, $output);
            $this->pushTags($output);
        }

        // Delete the release branch if not keeping
        if ($push) {
            $this->deleteRemoteBranch($releaseBranch, $output);
        }
        if (!$keep) {
            $this->deleteLocalBranch($releaseBranch, $output);
        }

        $output->writeln([
            "\nSummary of actions:",
            "- Latest objects have been fetched from 'origin'",
            "- Release branch '{$releaseBranch}' has been merged into '{$productionBranch}'",
            "- The release was tagged '{$tagName}'",
        ]);
        if ($keep) {
            $output->writeln("- Release branch '{$releaseBranch}' is still locally available");
        } else {
            $output->writeln("- Release branch '{$releaseBranch}' has been deleted");
        }

        if ($push) {
            $output->writeln([
                "- '{$productionBranch}' and tags have been pushed to 'origin'",
                "- Release branch '{$releaseBranch}' in 'origin' has been deleted",
            ]);
        }
    }

    /**
     * List all release branches
     *
     * @param OutputInterface $output
     * @param bool            $verbose
     */
    protected function listReleases(OutputInterface $output, bool $verbose): void
    {
        $releasePrefix = $this->getPrefix('release', $output);
        $productionBranch = 'production';

        $process = new Process(['git', 'branch', '--list', $releasePrefix . '*']);
        $process->run();
        $this->checkProcess($process, null, $output, true);

        // Process the output
        $releaseBranches = array_map('trim', explode("\n", $process->getOutput()));
        $releaseBranches = array_map(static function ($branch) {
            return str_replace('* ', '', $branch);
        }, $releaseBranches);
        $releaseBranches = array_filter($releaseBranches);

        // No release branches exist
        if (empty($releaseBranches)) {
            $output->writeln(["No release branches exist.",
                                "",
                                "You can start a new release branch with:",
                                "",
                                "    ans flow:release start <name>",
            ]);

            return;
        }

        $currentBranch = trim(shell_exec("git branch --show-current"));
        $shortNames = array_map(static function ($branch) use ($releasePrefix) {
            return substr($branch, strlen($releasePrefix));
        }, $releaseBranches);

        // release branches exist
        $width = max(array_map('strlen', $shortNames)) + 3;
        foreach ($shortNames as $branch) {
            $prefixedBranch = $releasePrefix . $branch;
            $isCurrent = ($prefixedBranch === $currentBranch);

            $output->write($isCurrent ? '* ' : '  ');

            // verbose option
            if ($verbose) {
                $output->write(sprintf("%-{$width}s", $branch));

                // Compare the release branch to the production branch
                $branchCommit = trim(shell_exec("git rev-parse {$prefixedBranch}"));
                $productionCommit = trim(shell_exec("git rev-parse {$productionBranch}"));
                $baseCommit = trim(shell_exec("git merge-base {$prefixedBranch} {$productionBranch}"));

                if ($branchCommit === $productionCommit) {
                    $output->writeln("(no commits yet)");
                } elseif ($baseCommit === $branchCommit) {
                    $output->writeln("(is behind production, may ff)");
                } elseif ($baseCommit === $productionCommit) {
                    $output->writeln("(based on latest production)");
                } else {
                    $output->writeln("(may be rebased)");
                }
            } else {
                $output->writeln($branch);
            }
        }
    }

    /**
     * Require a branch to exist
     *
     * @param string          $branch The name of the branch
     * @param OutputInterface $output The output interface
     *
     * @return void
     */
    protected function requireReleaseBranch(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'rev-parse', '--verify', $branch]);
        $process->run();
        $errormsg = "Branch '{$branch}' does not exist and is required.";
        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Require a branch to be absent
     *
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function requireRleaseBranchesAbsent(OutputInterface $output): void
    {
        $process = new Process(['git', 'branch', '--list', 'release/*']);
        $process->run();

        if ($process->isSuccessful() && !empty($process->getOutput())) {
            $releaseBranches = explode("\n", trim($process->getOutput()));
            $currentReleaseBranch = trim(current($releaseBranches), " *\t\n\r\0\x0B");
            $currentReleaseBranch = str_replace('release/', '', $currentReleaseBranch);

            $output->writeln("There is an existing release branch '{$currentReleaseBranch}'. Finish that one first.");
            exit(Command::FAILURE);
        }
    }

    /**
     * Checkout a branch
     *
     * @param string          $branch
     * @param OutputInterface $output
     */
    protected function checkoutBranch(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'checkout', $branch]);
        $process->run();
        $errormsg = "Could not checkout branch '{$branch}'.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Delete a remote branch
     *
     * @param string          $branch
     * @param OutputInterface $output
     */
    protected function deleteRemoteBranch(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'push', 'origin', '--delete', $branch]);
        $process->run(static function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });
        $errormsg = "Could not delete remote branch 'origin/{$branch}'.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Push a branch to origin
     *
     * @param string          $branch
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function pushBranch(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'push', '-u', 'origin', $branch]);
        $process->run(static function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });

        $this->checkProcess($process, null, $output, true);
    }

    protected function pushTags(OutputInterface $output): void
    {
        $process = new Process(['git', 'push', '--tags']);
        $process->run(static function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });
        $errormsg = "Could not push tags to 'origin'.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Check if a git tag exists
     *
     * @param string $name The name of the tag
     *
     * @return bool True if the tag exists, false otherwise
     */
    protected function tagExists(string $name): bool
    {
        $process = new Process(['git', 'tag', '-l', $name]);
        $process->run();

        return !empty(trim($process->getOutput()));
    }

    /**
     * Fetch changes from a remote branch
     *
     * @param string          $branch
     * @param OutputInterface $output
     */
    protected function fetchBranch(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'fetch', 'origin', $branch]);
        $process->run();
        $errormsg = "Could not fetch branch '{$branch}' from origin.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Ask for the tag message
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return string
     */
    protected function askForTagMessage(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $question = new Question('Enter tag message: ');
        $message = $helper->ask($input, $output, $question);

        if (empty($message)) {
            $output->writeln([
                "fatal: no tag message?",
                "Tagging failed. Please run finish again to retry.",
            ]);
            exit(Command::FAILURE);
        }

        return $message;
    }

    /**
     * Create a new tag
     *
     * @param string          $name
     * @param string          $message
     * @param OutputInterface $output
     */
    protected function createTag(string $name, string $message, OutputInterface $output): void
    {
        $command = ['git', 'tag', '-a', $name, '-m', $message];
        $process = new Process($command);
        $process->run();

        $this->checkProcess($process, null, $output, true);
    }

    /**
     * Check if a tag exists
     *
     * @param string          $name
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function requireNoExistingTags(string $name, OutputInterface $output): void
    {
        $process = new Process(['git', 'tag', '-l', $name]);
        $process->run();

        // If the output of the command is not empty, show error message
        if (!empty($process->getOutput())) {
            $output->writeln("Tag '{$name}' already exists.");
            exit(Command::FAILURE);
        }
    }
}
