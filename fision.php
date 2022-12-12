<?php
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
class Config
{
    const MAX_LEVEL_DEPTH = 1000;
    const ROOT_DIR = 'pages';
    const CACHE_ENABLE = false;
    const CACHE_DIR = 'cache';
}
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
class Fision {
    static public function start()
    {
        $route = Router::resolve($_SERVER['REQUEST_URI']);
        Cache::check($route);
        $response = new Response($route);
        Cache::generate($route, $response);
        echo $response->page();
        exit;
    }
}
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
        $dynamicPages = self::rglob(Config::ROOT_DIR . DIRECTORY_SEPARATOR . self::FILE_PATTERN);
        if (empty($dynamicPages)) {
            throw new Exception($path, 404);
        }
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

class State {
    public function __construct(private array $state = []){}
    public function push(array $new_data): self
    {
        foreach ($new_data as $data) {
            if (!empty($this->state[$data[0]])) continue;
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
}Fision::start();