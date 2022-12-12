<?php

namespace Fision;

use Fision\State;

class DataBlock
{
    const FISION_PATTERN = '~<!--\s*fision\s*((?:(?!-->).)*)\s*-->~s';
    const DATA_SEPARATOR_PATTERN = '~\s*:\s*~';

    private string $text = '';
    private array $data = [];

    public function toArray()
    {
        return $this->data;
    }

    public function toString()
    {
        return $this->text;
    }

    public function parse(State $state) :array
    {
        return $this->getData(
            $state->parse($this->text)
        );
    }

    public function extractFrom(string $content) : string
    {
        $this->text = $this->extractBlock($content);
        return $this->removeBlock($content);
    }

    private function extractBlock(string $content): string
    {
        preg_match_all(self::FISION_PATTERN, $content, $includes);
        return join("\n", $includes[1]);
    }

    private function removeBlock(string $content): string
    {
        return preg_replace(self::FISION_PATTERN, '', $content);
    }

    private function getData(string $content): array
    {
        $configuration = array_filter(
            explode("\n", $content),
            function ($config) {
                return !empty($config);
            }
        );

        $configuration = array_map(
            function ($config) {
                return explode(':', trim(preg_replace(self::DATA_SEPARATOR_PATTERN, ':', $config)));
            },
            $configuration
        );

        return $configuration;
    }
}