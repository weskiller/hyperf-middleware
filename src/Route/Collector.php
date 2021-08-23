<?php

namespace Weskiller\HyperfMiddleware\Route;

use Hyperf\HttpServer\Router\Handler;
use Hyperf\HttpServer\Router\RouteCollector as HyperfRouteCollector;
use Weskiller\HyperfMiddleware\Middleware\Collector as MiddleCollector;
class Collector extends HyperfRouteCollector
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
            MiddleCollector::addRouteMiddlewares(
                $this->server,
                $route,
                $method,
                $this->routeMiddlewareOption($options)
            );
        }
    }

    public function routeMiddlewareOption(array $options) :array
    {
        $middlewares = array_merge(
            $options['middleware'] ?? [],
            $options['middlewares'] ?? [],
            $options['without_middleware'] ?? [],
            $options['without_middlewares'] ?? []
        );

        return array_map([MiddleCollector::class, 'instanceMiddleWare'], $middlewares);
    }
}