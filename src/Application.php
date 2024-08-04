<?php
declare(strict_types=1);

namespace ANS_CLI;

use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    /**
     * ここで共通のオプションを追加する
     * $input->getOption("オプション名")で取得
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition(): \Symfony\Component\Console\Input\InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOptions($this->getCommonOptions());

        return $definition;
    }

    /**
     * getCommonOptions
     *
     * @access protected
     *
     * @return void
     */
    protected function getCommonOptions(): array | false
    {
        return [];
    }
}
