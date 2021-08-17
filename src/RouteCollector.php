<?php

namespace Weskiller\HyperfMiddleware;

use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\Handler;
use JetBrains\PhpStorm\Pure;
use Hyperf\HttpServer\Router\RouteCollector as HyperfRouteCollector;

class RouteCollector extends HyperfRouteCollector
{
    public function addRoute($httpMethod, string $route, $handler, array $options = [])
    {
        $route = $this->currentGroupPrefix . $route;
        $resolved = $this->routeParser->parse($route);
        $options = $this->mergeOptions($this->currentGroupOptions, $options);
        foreach ((array) $httpMethod as $method) {
            $method = strtoupper($method);
            foreach ($resolved as $data) {
                $this->dataGenerator->addRoute($method, $data, new Handler($handler, $route, $options));
            }
            MiddlewareManager::addMiddlewares($this->server, $route, $method, array_map([static::class,'makeMiddleWare'],$options['middleware'] ?? []));
        }
    }

    #[Pure]
    public static function makeMiddleWare(array|Middleware|string $middleware) :Middleware
    {
        if(is_string($middleware)) {
            return new Middleware($middleware);
        }
        if(is_array($middleware)) {
            return new Middleware(...$middleware);
        }
        return $middleware;
    }
}