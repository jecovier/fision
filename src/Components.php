<?php

namespace Fision;

class Components
{
    const BASE_TAG = '{component-tag}';
    const SINGLE_REPLACE_PATTERN = '~<\s*' . self::BASE_TAG . '\s*/>~';
    const DOUBLE_REPLACE_PATTERN = '~<\s*' . self::BASE_TAG . '\s*>((?:(?!' . self::BASE_TAG . ').)*)</\s*' . self::BASE_TAG . '\s*>~s';
    const SINGLE_SLOT_PATTERN = '~<\s*slot(?!slot).*/>~i';
    const DOUBLE_SLOT_PATTERN = '~<\s*slot(?!slot).*</\s*slot\s*>~i';

    public function render(array $data, string $content): string
    {
        return $this->replaceComponents(
            $this->getComponents($data),
            $content
        );
    }

    private function replaceSingleTagComponent(array $component, string $html, string $content):string
    {
        // inlcude tag replace
        $pattern = self::get_tag_pattern(self::SINGLE_REPLACE_PATTERN, $component[0]);
        return preg_replace(
            $pattern,
            $html,
            $content
        );
    }

    private function replaceComponents(array $components, string $content):string
    {
        foreach ($components as $component) {
            $filepath = Config::ROOT_DIR . DIRECTORY_SEPARATOR . $component[1];
            if (!file_exists($filepath)) {
                continue;
            }
            $html = file_get_contents($filepath);

            $content = self::replaceSingleTagComponent($component, $html, $content);
            $content = self::replaceDoubleTagComponent($component, $html, $content);
        }

        return $content;
    }

    private function replaceDoubleTagComponent(array $component, string $html, string $content):string
    {
        $pattern = self::get_tag_pattern(self::SINGLE_SLOT_PATTERN, $component[0]);
        $html = preg_replace($pattern, '\$1', $html);

        $pattern = self::get_tag_pattern(self::DOUBLE_SLOT_PATTERN, $component[0]);
        $html = preg_replace($pattern, '\$1', $html);

        // inlcude tag replace
        $pattern = self::get_tag_pattern(self::DOUBLE_REPLACE_PATTERN, $component[0]);
        return preg_replace(
            $pattern,
            $html,
            $content
        );
    }

    private function get_tag_pattern(string $pattern, string $tag): string
    {
        return str_replace(self::BASE_TAG, $tag, $pattern);
    }


    private function getComponents(array $configuration):array
    {
        return array_filter(
            $configuration,
            function ($component) {
                return str_ends_with($component[1], '.html');
            }
        );
    }
}