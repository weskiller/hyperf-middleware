<?php

namespace Weskiller\HyperfMiddleware\Route;

use Hyperf\HttpServer\Router\Handler;
use Hyperf\HttpServer\Router\RouteCollector as HyperfRouteCollector;
use Hyperf\Utils\ApplicationContext;
use Weskiller\HyperfMiddleware\Http\CoreMiddleware;
use Weskiller\HyperfMiddleware\Middleware\Collector as MiddleCollector;
use Weskiller\HyperfMiddleware\Middleware\Exclude;
use Weskiller\HyperfMiddleware\Middleware\Expect;
use Weskiller\HyperfMiddleware\Middleware\Middleware;
use Weskiller\HyperfMiddleware\Middleware\Without;
use Closure;
use RuntimeException;
use Throwable;

class Collector extends HyperfRouteCollector
{
    protected $currentGroupPrefix = '/';

    /**
     * @var array
     */
    protected array $currentGroupMiddlewares = [];

    public function addGroup(string $prefix, callable $callback, array $options = [])
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $currentGroupOptions = $this->currentGroupOptions;
        $currentGroupMiddlewares = $this->currentGroupMiddlewares;

        $this->currentGroupPrefix = self::prefixUri($previousGroupPrefix, $prefix);
        $middlewares =  $this->resolveMiddlewareOption($options);
        if($middlewares) {
            $this->currentGroupMiddlewares[] = $middlewares;
        }
        $this->currentGroupOptions = $this->mergeOptions($currentGroupOptions, $options);
        $callback($this);

        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentGroupOptions = $currentGroupOptions;
        $this->currentGroupMiddlewares = $currentGroupMiddlewares;

    }

    public function addRoute($httpMethod, string $route, $handler, array $options = []) :void
    {
        $route = self::prefixUri($this->currentGroupPrefix,$route);
        $resolved = $this->routeParser->parse($route);
        $middlewares = [
            MiddleCollector::getGlobalMiddlewares($this->server),
            ...$this->currentGroupMiddlewares,
            $this->resolveMiddlewareOption($options)
        ];
        $options = $this->mergeOptions($this->currentGroupOptions, $options);
        foreach ((array) $httpMethod as $method) {
            $method = strtoupper($method);
            foreach ($resolved as $data) {
                $this->dataGenerator->addRoute($method, $data, new Handler($handler, $route, $options));
            }
            if (!$handler instanceof Closure) {
                [$class, $classMethod] = CoreMiddleware::staticPrepareHandler($handler);
                $middlewares[] = MiddleCollector::getClassMiddlewares($class);
                $middlewares[] = MiddleCollector::getMethodMiddlewares($class, $classMethod);
            }
            MiddleCollector::addRouteMiddlewares(
                $this->server,
                $route,
                $method,
                array_merge(...$middlewares),
            );
        }
    }

    public function resolveMiddlewareOption(array &$options) :array
    {
        $middlewares = [];
        foreach ([Middleware::Option, Middleware::MultipleOption] as $key) {
            if(isset($options[$key])) {
                $middleware = $options[$key];
                if (is_string($middleware)) {
                    $middlewares[] = MiddleCollector::instanceMiddleWare($options[$key]);
                } else if($middleware instanceof Middleware) {
                    $middlewares[] = $middleware;
                } else if(is_array($middleware)) {
                    array_map(
                        static function($value) use(&$middlewares) {
                            $middlewares[] = MiddleCollector::instanceMiddleWare($value);
                        },
                        $options[$key]
                    );
                } else {
                    throw new RuntimeException('unknown middleware option');
                }
                unset($options[$key]);
            }
        }
        if(isset($options[Without::Option])) {
            $middlewares[] = ApplicationContext::getContainer()->get(Without::class);
            unset($options[Without::Option]);
        }
        if(isset($options[Exclude::Option])) {
            $middlewares[] = new Exclude((array) $options[Exclude::Option]);
            unset($options[Without::Option]);
        }
        if(isset($options[Expect::Option])) {
            $middlewares[] = new Expect((array) $options[Expect::Option]);
            unset($options[Without::Option]);
        }

        return $middlewares;
    }

    public static function prefixUri(string $prefix,string $path) :string
    {
        if ($path === '') {
            return $prefix;
        }
        if (str_starts_with($path,'/')) {
            return $prefix . $path;
        }
        return $prefix . '/' . $path;
    }
}