<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CommandUtils extends Command
{
    /**
     * Requires Git Flow to be initialized.
     *
     * @param OutputInterface $output The output interface.
     *
     * @return void
     */
    protected function requireGitFlowInitialized(OutputInterface $output): void
    {
        $process = new Process(['git', 'config', '--get', 'ansflow.branch.master']);
        $process->run();
        $errormsg = "fatal: Not a ansflow-enabled repo yet. Please run 'ans flow:init' first.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Checks the process execution and handles errors.
     *
     * @param Process         $process The process to check.
     * @param string|null     $message The error message to display.
     * @param OutputInterface $output  The output interface.
     * @param bool            $details Whether to display detailed error output.
     *
     * @return void
     */
    protected function checkProcess(Process $process, ?string $message, OutputInterface $output, bool $details): void
    {
        if (!$process->isSuccessful()) {
            if ($message !== null && !empty($message)) {
                $output->writeln($message);
            }

            if ($details && !empty($process->getErrorOutput())) {
                $output->writeln($process->getErrorOutput());
            }

            exit(Command::FAILURE);
        }
    }

    /**
     * Requires a clean working tree.
     *
     * @param OutputInterface $output The output interface.
     *
     * @return void
     */
    protected function requireCleanWorkingTree(OutputInterface $output): void
    {
        $process = new Process(['git', 'diff', '--quiet', '--exit-code']);
        $process->run();
        $errormsg = "Working tree is not clean. Please commit or stash your changes.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Gets the prefix for a given prefix type.
     *
     * @param string          $prefixType The prefix type.
     * @param OutputInterface $output     The output interface.
     *
     * @return string The prefix.
     */
    protected function getPrefix(string $prefixType, OutputInterface $output): string
    {
        $prefixKey = 'ansflow.prefix.' . $prefixType;
        $process = new Process(['git', 'config', '--get', $prefixKey]);
        $process->run();

        $this->checkProcess($process, null, $output, true);

        return trim($process->getOutput());
    }

    /**
     * Checks if a branch name exists.
     *
     * @param string|null $name   The branch name.
     * @param string      $usage  The usage message.
     * @param mixed       $output The output.
     *
     * @return void
     */
    protected function branchNameExists(?string $name, string $usage, mixed $output): void
    {
        if (empty($name)) {
            $output->writeln(["Missing argument <name>", $usage]);
            exit(Command::FAILURE);
        }
    }

    /**
     * Creates a new branch based on a base branch.
     *
     * @param string          $newBranch  The new branch name.
     * @param string          $baseBranch The base branch name.
     * @param OutputInterface $output     The output interface.
     *
     * @return void
     */
    protected function createBranch(string $newBranch, string $baseBranch, OutputInterface $output): void
    {
        $process = new Process(['git', 'checkout', '-b', $newBranch, $baseBranch]);
        $process->run();
        $errormsg = "Could not create branch: \n";

        $this->checkProcess($process, $errormsg, $output, true);
    }

    /**
     * Merges a source branch into a target branch.
     *
     * @param string          $sourceBranch The source branch name.
     * @param string          $targetBranch The target branch name.
     * @param OutputInterface $output       The output interface.
     *
     * @return void
     */
    protected function mergeBranch(string $sourceBranch, string $targetBranch, OutputInterface $output): void
    {
        $process = new Process(['git', 'checkout', $targetBranch]);
        $process->run(static function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });
        $this->checkProcess($process, null, $output, true);

        $process = new Process(['git', 'merge', '--no-ff', $sourceBranch]);
        $process->run();
        $this->checkProcess($process, null, $output, true);
    }

    /**
     * Deletes a local branch.
     *
     * @param string          $branch The branch name.
     * @param OutputInterface $output The output interface.
     *
     * @return void
     */
    protected function deleteLocalBranch(string $branch, OutputInterface $output): void
    {
        $process = new Process(['git', 'branch', '-d', $branch]);
        $process->run(static function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });
        $errormsg = "Could not delete local branch '{$branch}'.";

        $this->checkProcess($process, $errormsg, $output, false);
    }

    /**
     * Gets the latest commit hash of a branch.
     *
     * @param string          $branch The branch name.
     * @param OutputInterface $output The output interface.
     *
     * @return string The latest commit hash.
     */
    protected function getLatestCommitHash(string $branch, OutputInterface $output): string
    {
        $process = new Process(['git', 'rev-parse', '--short', $branch]);
        $process->run();

        $this->checkProcess($process, null, $output, true);

        return trim($process->getOutput());
    }
}
