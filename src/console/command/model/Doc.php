<?php

namespace mdoc\console\command\model;

class Doc
{
    protected function configure()
    {
        // 指令配置
        $this->setName('model:doc');
        // 设置参数
        $this->addArgument('model', Argument::OPTIONAL, "The model you want to gen doc.");

    }

    protected function execute(Input $input, Output $output)
    {
        $model = $input->getArgument('model');
        $output->writeln($model);
        // 指令输出
        $output->writeln(PHP_EOL.'Executed.');
    }
}
