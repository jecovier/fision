<?php

class Build
{
    const ROOT_DIR = '.' . DIRECTORY_SEPARATOR . 'src';
    const USE_STATEMENT_PATTERN = '~use\s+Fision.*;~i';
    const SINGLELINE_COMMENT_PATTERN = "~[^\:\"']//(?:(?!\*/)(?!//)[^\"'\\r\\n])*~is";
    const MULTILINE_COMMENT_PATTERN = '~/\*(?:(?!\*/).)*\*/~s';
    const BLANK_LINE_PATTERN = '/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/';
    const FISION_HEADER = <<<PHP
        <?php
        PHP;
    const FISION_FOOTER = <<<PHP
        Fision::start();
        PHP;

    public function __construct(string $filename)
    {
        $classes = $this->get_classes(self::ROOT_DIR);
        $content = self::FISION_HEADER;
        foreach ($classes as $class) {
            echo self::ROOT_DIR . DIRECTORY_SEPARATOR . $class . "\r\n";
            $content .= $this->clean_file(
                file_get_contents(self::ROOT_DIR . DIRECTORY_SEPARATOR . $class)
            );
        }
        $content .= self::FISION_FOOTER;

        file_put_contents($filename, $content);
    }

    private function get_classes(string $dir): array
    {
        return array_slice(scandir($dir), 2);
    }

    private function clean_file(string $file_content): string
    {
        $file_content = str_replace('<?php', '', $file_content);
        $file_content = str_replace('namespace Fision;', '', $file_content);
        $file_content = preg_replace(self::USE_STATEMENT_PATTERN, '', $file_content);
        $file_content = preg_replace(self::MULTILINE_COMMENT_PATTERN, '', $file_content);
        $file_content = preg_replace(self::SINGLELINE_COMMENT_PATTERN, '', $file_content);
        $file_content = preg_replace(self::BLANK_LINE_PATTERN, "\r\n", $file_content);
        return $file_content;
    }
}

new Build('fision.php');
