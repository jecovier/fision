<?php

namespace Fision;

class State {

    public function __construct(private array $state = []){}

    public function push(array $new_data): self
    {
        foreach ($new_data as $data) {
            if (!empty($this->state[$data[0]])) continue;

            // if (filter_var($data[1], FILTER_VALIDATE_URL)){
            //     $previous_data[$data[0]] = self::getDataFromURL($data[1]);
            //     continue;
            // }

            // if (self::isValidFile($data[1])){
            //     $previous_data[$data[0]] = self::getDataFromFile($data[1]);
            //     continue;
            // }

            $this->state[$data[0]] = $data[1];
        }

        return $this;
    }


    public function parse(string $content): string
    {
        return str_replace(
            array_map(function ($key) {
                return '{{' . $key . '}}';
            }, array_keys($this->state)),
            array_values($this->state),
            $content
        );
    }
}