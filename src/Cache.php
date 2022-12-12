<?php

namespace Fision;

use Fision\Config;

class Cache
{
    static public function check(Route $route)
    {
        if (!Config::CACHE_ENABLE) return;

        $cache_file = self::get_filepath($route->file);
        if (file_exists($cache_file)) {
            readfile($cache_file);
            exit;
        }
    }

    static public function generate(Route $route, Response $response)
    {
        if (!Config::CACHE_ENABLE) return;

        $cache_file = self::get_filepath($route->file);
        $dirname = dirname($cache_file);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        $cached = fopen($cache_file, 'w');
        if (!$cached) return;
        fwrite($cached, $response->page());
        fclose($cached);
    }

    static private function get_filepath(string $filepath): string
    {
        return preg_replace('~^' . Config::ROOT_DIR . '~i', Config::CACHE_DIR, $filepath);
    }
}
