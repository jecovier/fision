<?php

namespace Fision;

use Fision\Config;
use Fision\State;
use Fision\DataBlock;

class Response
{
    private string $generated_page = '';

    public function __construct(Route $route)
    {
        $this->generated_page = $this->parse(
            $route->page,
            $route->params
        );
    }

    public function page():string
    {
        return $this->generated_page;
    }

    public function parse(string $content, array $original_data, int $max_level_depth = Config::MAX_LEVEL_DEPTH):string
    {
        $state = new State($original_data);
        $dataBlock = new DataBlock();
        $components = new Components();

        for ($i = 0; $i < $max_level_depth; $i++) {
            $content = $dataBlock->extractFrom($content);

            if (empty($dataBlock->toString())) {
                break;
            }

            $data = $dataBlock->parse($state);
            $state->push($data);
            $content = $components->render($data,$content);
        }

        $content = $state->parse($content);
        return $content;
    }
}