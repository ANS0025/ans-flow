<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use ANS_CLI\Console\FlowCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class FeatureCommand extends FlowCommand
{
    private string $usage = "usage: ans flow:feature [list] [-v]\n" .
                     "       ans flow:feature start <name> [<baseBranch>]\n" .
                     "       ans flow:feature finish [-k] <name|nameprefix> [<targetBranch>]"
                     ;

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
            ->addArgument('baseBranch', InputArgument::OPTIONAL, 'The base branch to start or finish the feature')
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
        $baseBranch = $input->getArgument('baseBranch');
        $keep = $input->getOption('keep');
        $verbose = $input->getOption('verbose');

        switch ($action) {
            case 'start':
                $this->startFeature($name, $baseBranch, $output);
                break;
            case 'finish':
                $this->finishFeature($name, $baseBranch, $keep, $output);
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
     * @param string|null     $baseBranch
     * @param OutputInterface $output
     */
    protected function startFeature(?string $name, ?string $baseBranch, OutputInterface $output): void
    {
        $baseBranch = $baseBranch ??= 'production';
        $featurePrefix = $this->getPrefix('feature', $output);
        $featureBranch = $featurePrefix . $name;

        // Sanity checks
        $this->branchNameExists($name, $this->usage, $output);
        $this->requireFeatureBranchAbsent($featureBranch, $output);

        // Create the feature branch based on the specified base branch
        $this->createBranch($featureBranch, $baseBranch, $output);

        // Output the summary
        $output->writeln([
            "Switched to a new branch '{$featureBranch}'",
            "",
            "Summary of actions:",
            "- A new branch '{$featureBranch}' was created, based on '{$baseBranch}'",
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
     * @param string|null     $targetBranch
     * @param bool            $keep
     * @param OutputInterface $output
     */
    protected function finishFeature(?string $name, ?string $targetBranch, bool $keep, OutputInterface $output): void
    {
        $targetBranch = $targetBranch ??= 'main';
        $featurePrefix = $this->getPrefix('feature', $output);
        $featureBranch = $featurePrefix . $name;

        // Sanity checks
        $this->branchNameExists($name, $this->usage, $output);
        $this->requireFeatureBranch($featureBranch, $name, $output);
        $this->requireCleanWorkingTree($output);

        // Get the commit hash of the latest commit on the feature branch
        $commitHash = $this->getLatestCommitHash($featureBranch, $output);

        // Merge into the specified target branch
        $this->mergeBranch($featureBranch, $targetBranch, $output);

        // Delete the feature branch if not keeping
        if (!$keep) {
            $this->deleteLocalBranch($featureBranch, $output);
        }

        $output->writeln(["Switched to branch '{$targetBranch}'",
                         "Your branch is up to date with 'origin/{$targetBranch}'.",
        "Already up to date.", ]);
        if (!$keep) {
                $output->writeln("Deleted branch {$featureBranch} (was {$commitHash}).");
        }
        $output->writeln("\nSummary of actions:");
        $output->writeln("- The feature branch '{$featureBranch}' was merged into '{$targetBranch}'");
        if ($keep) {
            $output->writeln("- Feature branch '{$featureBranch}' is still available");
        } else {
            $output->writeln("- Feature branch '{$featureBranch}' has been removed");
        }
        $output->writeln("- You are now on branch '{$targetBranch}'\n");
    }

    /**
     * List all feature branches
     *
     * @param OutputInterface $output
     * @param bool            $verbose
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
     * @param string|null $name   The name of the branch without prefix
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
     * @param string|null $branch The name of the branch
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
}
