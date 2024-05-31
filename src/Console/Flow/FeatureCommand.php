<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class FeatureCommand extends CommandUtils
{
    /**
     * configure
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('flow:feature')
            ->setDescription('Manage feature branches')
            ->addArgument('action', InputArgument::OPTIONAL, 'The action to perform (start, finish, list)', 'list')
            ->addArgument('name', InputArgument::OPTIONAL, 'The feature branch name')
            ->addOption('keep', 'k', InputOption::VALUE_NONE, 'Keep branch after performing finish')
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
        $keep = $input->getOption('keep');
        $verbose = $input->getOption('verbose');

        switch ($action) {
            case 'start':
                $this->startFeature($name, $output);
                break;
            case 'finish':
                $this->finishFeature($name, $keep, $output);
                break;
            case 'list':
                $this->listFeatures($output, $verbose);
                break;
            default:
                $output->writeln(["Unknown subcommand: '$action'", $this->usage]);

                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Start a new feature branch
     *
     * @param string          $name
     * @param bool            $fetch
     * @param OutputInterface $output
     */
    protected function startFeature(?string $name, OutputInterface $output): void
    {
        $productionBranch = 'production';
        $featurePrefix = $this->getPrefix('feature', $output);
        $featureBranch = $featurePrefix . $name;

        // Sanity checks
        $this->branchNameExists($name, $this->usage, $output);
        $this->requireFeatureBranchAbsent($featureBranch, $output);

        // Create the feature branch based on the production branch
        $this->createBranch($featureBranch, $productionBranch, $output);

        // Output the summary
        $output->writeln([
            "Switched to a new branch '{$featureBranch}'",
            "",
            "Summary of actions:",
            "- A new branch '{$featureBranch}' was created, based on '{$productionBranch}'",
            "- You are now on branch '{$featureBranch}'",
            "",
            "Now, start committing on your feature. When done, use:",
            "",
            "    ans flow:feature finish {$name}",
            "",
        ]);
    }

    /**
     * Finish a feature branch
     *
     * @param string          $name
     * @param bool            $fetch
     * @param bool            $rebase
     * @param bool            $keep
     * @param bool            $forceDelete
     * @param bool            $squash
     * @param OutputInterface $output
     */
    protected function finishFeature(?string $name, bool $keep, OutputInterface $output): void
    {
        $mainBranch = 'main';
        $featurePrefix = $this->getPrefix('feature', $output);
        $featureBranch = $featurePrefix . $name;

        // Sanity checks
        $this->branchNameExists($name, $this->usage, $output);
        $this->requireFeatureBranch($featureBranch, $name, $output);
        $this->requireCleanWorkingTree($output);

        // Get the commit hash of the latest commit on the feature branch
        $commitHash = $this->getLatestCommitHash($featureBranch, $output);

        // Merge into main branch
        $this->mergeBranch($featureBranch, $mainBranch, $output);

        // Delete the feature branch if not keeping
        if (!$keep) {
            $this->deleteLocalBranch($featureBranch, $output);
        }

        $output->writeln(["Switched to branch '{$mainBranch}'",
                         "Your branch is up to date with 'origin/{$mainBranch}'.",
        "Already up to date.", ]);
        if (!$keep) {
                $output->writeln("Deleted branch {$featureBranch} (was {$commitHash}).");
        }
        $output->writeln("\nSummary of actions:");
        $output->writeln("- The feature branch '{$featureBranch}' was merged into '{$mainBranch}'");
        if ($keep) {
            $output->writeln("- Feature branch '{$featureBranch}' is still available");
        } else {
            $output->writeln("- Feature branch '{$featureBranch}' has been removed");
        }
        $output->writeln("- You are now on branch '{$mainBranch}'\n");
    }

    /**
     * List all feature branches
     *
     * @param OutputInterface $output
     */
    protected function listFeatures(OutputInterface $output, bool $verbose): void
    {
        $featurePrefix = $this->getPrefix('feature', $output);
        $productionBranch = 'production';

        $process = new Process(['git', 'branch', '--list', $featurePrefix . '*']);
        $process->run();
        $this->checkProcess($process, null, $output, true);

        // Process the output
        $featureBranches = array_map('trim', explode("\n", $process->getOutput()));
        $featureBranches = array_map(static function ($branch) {
            return str_replace('* ', '', $branch);
        }, $featureBranches);
        $featureBranches = array_filter($featureBranches);

        // No feature branches exist
        if (empty($featureBranches)) {
            $output->writeln(["No feature branches exist.",
                                "",
                                "You can start a new feature branch with:",
                                "",
                                "    git flow feature start <name>",
                                "",
            ]);

            return;
        }

        $currentBranch = trim(shell_exec("git branch --show-current"));
        $shortNames = array_map(static function ($branch) use ($featurePrefix) {
            return substr($branch, strlen($featurePrefix));
        }, $featureBranches);

        // Feature branches exist
        $width = max(array_map('strlen', $shortNames)) + 3;
        foreach ($shortNames as $branch) {
            $prefixedBranch = $featurePrefix . $branch;
            $isCurrent = ($prefixedBranch === $currentBranch);

            $output->write($isCurrent ? '* ' : '  ');

            // verbose option
            if ($verbose) {
                $output->write(sprintf("%-{$width}s", $branch));

                // Compare the feature branch to the production branch
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
     * Check if a feature branch name is empty
     *
     * @param string|null $branch The name of the branch with prefix
     * @param string|null $output The name of the branch without prefix
     *
     * @return void
     */
    protected function requireFeatureBranch(string $branch, string $name, OutputInterface $output): void
    {
        $process = new Process(['git', 'rev-parse', '--verify', $branch]);
        $process->run();
        $errormsg = "No branch matches '{$name}'";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Require a branch to be absent
     *
     * @param string|null $name   The name of the branch
     * @param mixed       $output The output interface
     *
     * @return void
     */
    protected function requireFeatureBranchAbsent(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'rev-parse', '--verify', $branch]);
        $process->run();

        if ($process->isSuccessful()) {
            $output->writeln("Branch '{$branch}' already exists. Pick another name.");
            exit(Command::FAILURE);
        }
    }

    private $usage = "usage: ans flow:feature [list] [-v]\n" .
                     "       ans flow:feature start <name>\n" .
                     "       ans flow:feature finish [-k] <name|nameprefix>"
                     ;
}
