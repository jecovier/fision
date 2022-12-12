<?php

namespace Fision;

use Fision\Router;
use Fision\Cache;
use Fision\Response;

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