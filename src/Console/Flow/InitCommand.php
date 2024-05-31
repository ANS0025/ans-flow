<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

class InitCommand extends Command
{
    /**
     * configure
     *
     * @access protected
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('flow:init')
            ->setDescription("Initialize ansflow")
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force setting of ansflow branches, even if already configured (default: false)')
            ->addOption('default', 'd', InputOption::VALUE_NONE, 'Use default branch naming conventions (default: false)')
        ;
    }

    /**
     * execute
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @access protected
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the directory is a git repository
        $gitCheckProcess = new Process(['git', 'rev-parse', '--is-inside-work-tree']);
        $gitCheckProcess->run();
        if (!$gitCheckProcess->isSuccessful()) {
            // Initialize git
            $gitInitProcess = new Process(['git', 'init']);
            $gitInitProcess->run();
            if (!$gitInitProcess->isSuccessful()) {
                $output->writeln('Failed to initialize git.');

                return Command::FAILURE;
            }
            $output->writeln([
                $gitInitProcess->getOutput(),
                'No branches exist yet. Base branches must be created now.',
            ]);
        }

        $helper = $this->getHelper('question');
        $useForce = $input->getOption('force');
        $useDefault = $input->getOption('default');

        // Check if ansflow is already initialized
        $process = new Process(['git', 'config', '--local', '--get', 'ansflow.branch.master']);
        $process->run();
        if ($process->isSuccessful() && !$useForce) {
            $output->writeln("Already initialized for ansflow.\nTo force reinitialization, use: ans flow:init -f");

            return Command::SUCCESS;
        }

        // Set up branches and prefixes
        if ($useDefault) {
            $masterBranch = 'production';
            $developBranch = 'main';
            $featurePrefix = 'feature/';
            $releasePrefix = 'release/';
            $output->writeln('Using default branch names.');
        } else {
            $localBranches = $this->getLocalBranches();

            if ($useForce) {
                $output->writeln("Which branch should be used for bringing forth production releases?");
                foreach ($localBranches as $branch) {
                    $output->writeln("   - $branch");
                }
            }
            $masterBranch = $helper->ask($input, $output, new Question('Branch name for production releases: [production] ', 'production'));
            if ($useForce && !in_array($masterBranch, $localBranches)) {
                $output->writeln("Local branch '$masterBranch' does not exist.");

                return Command::FAILURE;
            }

            if ($useForce) {
                $output->writeln("\nWhich branch should be used for integration of the \"next release\"?");
                foreach ($localBranches as $branch) {
                    if ($branch !== $masterBranch) {
                        $output->writeln("   - $branch");
                    }
                }
            }
            $developBranch = $helper->ask($input, $output, new Question('Branch name for "next release" development: [main] ', 'main'));
            if ($useForce && !in_array($developBranch, $localBranches)) {
                $output->writeln("Local branch '$developBranch' does not exist.");

                return Command::FAILURE;
            }

            $output->writeln("\nHow to name your supporting branch prefixes?");
            $featurePrefix = $helper->ask($input, $output, new Question('Feature branch prefix: [feature/] ', 'feature/'));
            $releasePrefix = $helper->ask($input, $output, new Question('Release branch prefix: [release/] ', 'release/'));
        }

        // Create the production branch if it doesn't exist
        $process = new Process(['git', 'rev-parse', '--verify', $masterBranch]);
        $process->run();
        if (!$process->isSuccessful()) {
            // Check if there are any commits in the repository
            $commitCountProcess = new Process(['git', 'rev-list', '--count', 'HEAD']);
            $commitCountProcess->run();
            $commitCount = intval(trim($commitCountProcess->getOutput()));

            if ($commitCount === 0) {
                // If there are no commits, create an initial empty commit
                $process = new Process(['git', 'commit', '--allow-empty', '-m', 'Initial commit']);
                $process->run();
            }

            // Create the production branch
            $process = new Process(['git', 'branch', $masterBranch]);
            $process->run();

            if (!$process->isSuccessful()) {
                $output->writeln([
                    'Failed to create production branch.',
                    $process->getErrorOutput(),
                ]);

                return Command::FAILURE;
            }
        }

        // Create the develop branch if it doesn't exist
        $process = new Process(['git', 'rev-parse', '--verify', $developBranch]);
        $process->run();
        if (!$process->isSuccessful()) {
            // Check if there are any commits in the repository
            $commitCountProcess = new Process(['git', 'rev-list', '--count', 'HEAD']);
            $commitCountProcess->run();
            $commitCount = intval(trim($commitCountProcess->getOutput()));

            if ($commitCount === 0) {
                // If there are no commits, create an initial empty commit
                $process = new Process(['git', 'commit', '--allow-empty', '-m', 'Initial commit']);
                $process->run();
            }

            // Create the develop branch based on the production branch
            $process = new Process(['git', 'branch', $developBranch, $masterBranch]);
            $process->run();

            if (!$process->isSuccessful()) {
                $output->writeln([
                    'Failed to create develop branch.',
                    $process->getErrorOutput(),
                ]);

                return Command::FAILURE;
            }
        }

        // Checkout to develop branch
        $process = new Process(['git', 'checkout', $developBranch]);
        $process->run();
        if (!$process->isSuccessful()) {
            $output->writeln([
                'Failed to checkout to develop branch.',
                $process->getErrorOutput(),
            ]);

            return Command::FAILURE;
        }

        // Set the ansflow configuration
        $process = new Process(['git', 'config', 'ansflow.branch.master', $masterBranch]);
        $process->run();
        $process = new Process(['git', 'config', 'ansflow.branch.develop', $developBranch]);
        $process->run();
        $process = new Process(['git', 'config', 'ansflow.prefix.feature', $featurePrefix]);
        $process->run();
        $process = new Process(['git', 'config', 'ansflow.prefix.release', $releasePrefix]);
        $process->run();

        $output->writeln('ANSflow initialized successfully.');

        return Command::SUCCESS;
    }

    private function getLocalBranches(): array
    {
        $process = new Process(['git', 'branch']);
        $process->run();

        $output = $process->getOutput();
        $branches = explode("\n", $output);
        $branches = array_map('trim', $branches);
        $branches = array_filter($branches);

        // Remove the asterisk from the current branch
        $branches = array_map(static function ($branch) {
            return ltrim($branch, '* ');
        }, $branches);

        return $branches;
    }
}
