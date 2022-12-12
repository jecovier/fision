<?php

namespace Fision;

use Fision\Config;
use Exception;
use Throwable;

class Router
{
    const PARAMS_PATTERN = '\{([^\}]*)\}';
    const REPLACE_PATTERN = '(\{[^\}]*\})';
    const PARAMS_VALUE_PATTERN = '([a-zA-Z\d]*)';
    const FILE_PATTERN = DIRECTORY_SEPARATOR . '*{*.html';
    const DOUBLE_DIRECTORY_SEPARATOR = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;

    static public function resolve(string $path): Route
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, trim(strtolower(urldecode($path)), '/'));
        $base_path = Config::ROOT_DIR . DIRECTORY_SEPARATOR . $path;

        $file = $base_path . '.html';
        if (file_exists($file)) {
            return new Route($path, $file);
        }

        $file = rtrim($base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
        if (file_exists($file)) {
            return new Route($path, $file);
        }

        try {
            return self::lookForDynamicRoute($path);
        } catch (Throwable $e) {
            $file = DIRECTORY_SEPARATOR . '404.html';
            return new Route($path, $file);
        }
    }

    static private function lookForDynamicRoute(string $path):Route
    {
        /**
         * If there isn't any dynamic route, requested uri is
         * just wrong. It will return a 404 exception
         */
        $dynamicPages = self::rglob(Config::ROOT_DIR . DIRECTORY_SEPARATOR . self::FILE_PATTERN);
        if (empty($dynamicPages)) {
            throw new Exception($path, 404);
        }
        /**
         * Try to find a dynamic route that could match requested
         * path. If no one is found we throw a 404 exception
         */
        return self::findDynamicRoute($dynamicPages, $path);
    }

    static private function findDynamicRoute(array $dynamicPages, string $path): Route
    {
        foreach ($dynamicPages as $route) {
            $pattern = str_replace([Config::ROOT_DIR  . DIRECTORY_SEPARATOR, '.html'], ['', ''], $route);

            preg_match_all('~' . self::PARAMS_PATTERN . '~', $pattern, $params_keys);
            $pattern = '~' . preg_replace(
                self::REPLACE_PATTERN,
                self::PARAMS_VALUE_PATTERN,
                $pattern
            ) . '~';

            if (preg_match($pattern, $path, $params_values)) {
                array_shift($params_values);
                return new Route(
                    $path,
                    $route,
                    array_combine($params_keys[1], $params_values),
                );
                break;
            }
        }

        /**
         * There is not matching dynamic route,
         * we throw a 404 error
         */
        throw new Exception($path, 404);
    }

    static private function rglob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge(
                [],
                ...[$files, self::rglob($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags)]
            );
        }
        return $files;
    }
}
