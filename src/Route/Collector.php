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
use Weskiller\HyperfMiddleware\Middleware\Direct;
use Closure;
use RuntimeException;

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
                try {
                    @([$class, $classMethod] = CoreMiddleware::staticPrepareHandler($handler));
                } catch (RuntimeException $e) {
                    throw new RuntimeException(sprintf('Route: "%s" ,Handler parser error.',$route));
                }
                if($class) {
                    $middlewares[] = MiddleCollector::getClassMiddlewares($class);
                    if($classMethod) {
                        $middlewares[] = MiddleCollector::getMethodMiddlewares($class, $classMethod);
                    }
                }
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

        if(isset($options[Middleware::Option])) {
            $middlewares[] = MiddleCollector::instanceMiddleWare($options[Middleware::Option]);
            unset($options[Middleware::Option]);
        }

        if(isset($options[Middleware::MultipleOption])) {
            $middleware = $options[Middleware::MultipleOption];
            if (is_string($middleware)) {
                $middlewares[] = MiddleCollector::instanceMiddleWare($options[Middleware::MultipleOption]);
            } else if($middleware instanceof Middleware) {
                $middlewares[] = $middleware;
            } else if(is_array($middleware)) {
                array_map(
                    static function($value) use(&$middlewares) {
                        $middlewares[] = MiddleCollector::instanceMiddleWare($value);
                    },
                    $options[Middleware::MultipleOption]
                );
            } else {
                throw new RuntimeException('unknown middleware option');
            }
            unset($options[Middleware::MultipleOption]);
        }

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
        if(isset($options[Direct::Option])) {
            $middlewares[] = ApplicationContext::getContainer()->get(Direct::class);
            unset($options[Direct::Option]);
        }
        if(isset($options[Exclude::Option])) {
            $middlewares[] = new Exclude((array) $options[Exclude::Option]);
            unset($options[Direct::Option]);
        }
        if(isset($options[Expect::Option])) {
            $middlewares[] = new Expect((array) $options[Expect::Option]);
            unset($options[Direct::Option]);
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