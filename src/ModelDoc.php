<?php

namespace mdoc;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\App;


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
        defined('APP_PATH') OR define('APP_PATH', App::getAppPath());

        $model = $input->getArgument('model');
        $model = trim($model, DIRECTORY_SEPARATOR);

        self::syncModelDoc($model);

        // 指令输出
        $output->writeln(PHP_EOL.'Finished.');
    }

    /**
     * 同步模型的文档注释
     * @param string $FN
     * @param null $FP
     */
    private static function syncModelDoc($FN = '', $FP = null)
    {
        // 如果未指定文件目录则认为基于应用目录
        $FP || $FP = APP_PATH;
        // File Full Path Name.
        $FF = $FP.$FN;

        if (!file_exists($FF)) {
            // 该 文件/目录 不存在，直接忽略

            return;
        }

        if (is_dir($FF)) {
            // 目标为目录，需要对目录进行解析
            $DIR = dir($FF);
            while (false !== ($file = $DIR->read())) {
                if ($file === '.' || $file === '..') {
                    // 当前目录与上级目录不能算在内
                    continue;
                }
                self::syncModelDoc($file, $FF);
            }
            $DIR->close();

            return;
        }

        // 通过后即为合法的模型文件
        $pi = pathinfo($FF);
        // isset($pi['extension'])

        if (is_file($FF)) {
            // 目标为文件，可以对文件进行解析
            $text = file_get_contents($FF);

            // 诸多判断是不是合法的模型文件
            if (empty($text)) {
                // 空内容，直接忽略

                return;
            }

            $namespace = '/^\s*namespace\s+([\a-zA-Z]+?);$/m';
            $exists = preg_match($namespace, $text, $m);
            if (!$exists) {
                // namespace 。。。 不存在，直接忽略

                return;
            }


            // 命名空间 + 文件名称 即为该 Model 的 Name
            $cn = $m[1].'\\'.$pi['filename'];
            if (!class_exists($cn) || !is_subclass_of($cn, 'think\model')) {
                // 该类不存在 或者不是模型类

                return;
            }

            // 利用模型执行查询
            $model = new $cn();
            $sql = "show create table `{$model->getTable()}`";
            $res = $model->query($sql);
            if (empty($res[0]) || empty($res[0]['Create Table'])) {
                // 若指定结果不存在

                return;
            }

            $doc = self::mkCreateTableToDocStr($res[0]['Create Table']);
            $txt = self::updateFileTextWithDoc($text, $doc, $model->getTableFields());

            file_put_contents($FF, $txt);
        }

        return;
    }

    /**
     * Create Table 的结果 转成 文档需要的字符串
     * @param $str
     * @return string
     */
    private static function mkCreateTableToDocStr($str)
    {
        $pattern = [
            '/^CREATE TABLE.*?$/m',
            '/^\s*UNIQUE KEY .*?$/m',
            '/^\s+PRIMARY KEY.*?$/m',
            '/^\) .*?$/m',
            '/^\s*`(.*?)`/m',
            '/,$/m',
            "/(\s*?\r?\n\s*?)+/",
            '/\s$/',
        ];
        $replace = [
            '',
            '',
            '',
            '',
            ' * @property \$$1',
            '',
            "\n",
            '',
        ];

        return preg_replace($pattern, $replace, $str);
    }

    /**
     * 根据注释内容更新文件文本
     * @param string $text
     * @param string $doc
     * @param array $fields
     * @return string
     */
    private static function updateFileTextWithDoc($text, $doc, Array $fields)
    {
        $pattern = "/(\/\*\*(?:.|\n)*?\*\/)\s*\n\s*class/";
        if (preg_match($pattern, $text, $notes)) {
            // 已经有注释了，需要在已经有的注释上进行调整
            $before = $notes[1]; // 这部分完完整整的当前注释
            $pattern = $replace = [];
            foreach ($fields as $field) {
                $pattern[] = '/^[\s*]*@property\s+\$'.$field.'\s+.*?$\n/m';
                $replace[] = '';
            }
            $pattern[] = '/^[\s*]*$\n/m';
            $replace[] = '';
            $pattern[] = '/(^[\s\*]*\/$)/m';
            $replace[] = " * \n{$doc}\n * \n$1";
            $after = preg_replace($pattern, $replace, $before);

            return str_replace($before, $after, $text);
        }

        // 还没有注释，需要生成一个新的
        $pattern = '/^[\s]*(class\s*\w+\s*extends\s*\w+)$/im';
        if (preg_match($pattern, $text, $match)) {
            $classLine = preg_replace('/\s+/', ' ', $match[1]);
            $text = preg_replace($pattern, $classLine, $text);
            $replace = "\n/**\n * \n{$doc}\n * \n */\n";

            return str_replace($classLine, $replace.$classLine, $text);
        }

        return $text;
    }
}
