<?php

namespace Fision;

class Route
{
    public string $page = '';

    public function __construct(
        public string $uri, 
        public string $file, 
        public array $params = []
    ) {
        $this->page = file_get_contents($file);
    }
}
