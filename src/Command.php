<?php
declare(strict_types=1);

namespace ANS_CLI;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Command extends SymfonyCommand
{
    public array $config = []; // configure
    public $input = null;
    public $output;
    public $questionHelper;

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
     *
     * @access protected
     *
     * @return bool
     */
    protected function askYesOrNo(string $message, $default = false): bool
    {
        $question = new ConfirmationQuestion($message, $default);

        return !$this->isDryRun ? $this->questionHelper->ask($this->input, $this->output, $question) : true;
    }
}
