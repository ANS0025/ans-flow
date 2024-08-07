<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use ANS_CLI\Console\FlowCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends FlowCommand
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
            ->setName('version')
            ->setDescription("Display ans flow version")
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
        $output->writeln(\ANS_CLI_VERSION);

        return Command::SUCCESS;
    }
}
