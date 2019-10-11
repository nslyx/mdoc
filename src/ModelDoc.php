<?php

namespace mdoc\console\command\model;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class ModelDoc extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('optimize:model:doc');
        // 设置描述
        $this->setDescription('Sync Model Doc with DB Tables');
        // 设置参数
        $this->addArgument('model', Argument::OPTIONAL, "Sync the Doc with DB Tables for this Model");
    }

    protected function execute(Input $input, Output $output)
    {
        $model = $input->getArgument('model');
        $output->writeln($model);
        // 指令输出
        $output->writeln(PHP_EOL.'Executed.');
    }
}
