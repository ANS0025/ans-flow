<?php
declare(strict_types=1);

namespace ANS_CLI\Console\Flow;

use ANS_CLI\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FlowCommand extends Command
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
            ->setName('flow')
            ->setDescription("Git flow customized for ANS Inc. branch strategy")
            ->addUsage($this->usage)
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
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            'usage: ans flow:<action>',
            '',
            $this->usage,
        ]);

        return Command::FAILURE;
    }

    private $usage = 'Available subcommands are:' . "\n" .
                     '   flow:init      Initialize a new git repo with support for the branching model.' . "\n" .
                     '   flow:feature   Manage your feature branches.' . "\n" .
                     '   flow:release   Manage your release branches.' . "\n" .
                     '   flow:version   Shows version information.' . "\n" .
                     '' . "\n" .
                     'Try `git flow:<action> --help` for details.';
}
