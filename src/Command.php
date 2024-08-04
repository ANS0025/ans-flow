<?php
declare(strict_types=1);

namespace ANS_CLI;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Command extends SymfonyCommand
{
     /** @var array<string, mixed> */
    protected array $config = []; //configure
    protected ?InputInterface $input = null;
    protected ?OutputInterface $output;
    protected ?QuestionHelper $questionHelper;

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

        // configure
        $this->config = [
            'version' => \ANS_CLI_VERSION,
        ];

        // load
        $this->questionHelper = new QuestionHelper();
    }

    /**
     * askYesOrNo
     *
     * @param string $message Message
     * @param bool   $default Default
     *
     * @access protected
     *
     * @return bool
     */
    protected function askYesOrNo(string $message, bool $default = false): bool
    {
        $question = new ConfirmationQuestion($message, $default);

        return !$this->isDryRun ? $this->questionHelper->ask($this->input, $this->output, $question) : true;
    }
}
