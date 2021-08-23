<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Middleware;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\MetadataCollectorInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Arr;
use JetBrains\PhpStorm\Pure;

class Collector implements MetadataCollectorInterface
{
    protected static array $global = [];
    protected static array $collect = [];
    protected static array $servers = [];
    protected static array $compiled = [];

    public static function get(string $key, $default = null)
    {
        return null;
    }

    public static function set(string $key, $value): void
    {
    }

    public static function clear(?string $key = null): void
    {
    }

    public static function serialize(): string
    {
        return serialize(self::$collect);
    }

    public static function deserialize(string $metadata): bool
    {
        static::$collect = unserialize($metadata,['allowed_classes' => [Middleware::class,WithoutMiddleware::class]]);
        return true;
    }

    public static function list(): array
    {
        return [];
    }

    /**
     * @param string $class
     * @param Middleware|WithoutMiddleware $middleware
     */
    public static function collectClass(string $class, Middleware|WithoutMiddleware $middleware): void
    {
        static::$collect['c'][$class][] = $middleware;
    }

    /**
     * @param string $class
     *
     * @param string $method
     * @param Middleware|WithoutMiddleware $middleware
     */
    public static function collectMethod(string $class, string $method, Middleware|WithoutMiddleware $middleware): void
    {
        static::$collect['m'][$class][$method][] = $middleware;
    }


    public static function addRouteMiddlewares(string $server,string $class,string $method,array $middlewares) :void
    {
        foreach ($middlewares as $middleware) {
            self::$servers[$server][$class][$method][] = $middleware;
        }
    }

    public static function global(string $server) :array
    {
        if(isset(self::$global[$server])) {
            self::$global[$server] = array_map(
                [static::class,'instanceMiddleWare'],
                (array) ApplicationContext::getContainer()->get(ConfigInterface::class)->get('middlewares')
            );
        }
        return self::$global[$server];
    }

    public static function compile(string $server) :array
    {
        if(!isset(self::$compiled[$server])) {
            $globalMiddlewares = self::global($server);
            collect(self::$servers[$server] ?? [])
                ->map(static function (array $methods, $class) use ($globalMiddlewares, $server) {
                    $classMiddlewares = self::$collect['c'][$class];
                    collect($methods)
                        ->map(static function (array $routeMiddlewares, string $method) use (
                            $server,
                            $globalMiddlewares,
                            $classMiddlewares,
                            $class
                        ) {
                            $annotationMiddlewares = self::$collect['m'][$class][$method];
                            $middlewares = collect(array_merge(
                                $globalMiddlewares,
                                $classMiddlewares,
                                $routeMiddlewares,
                                $annotationMiddlewares
                            ));
                            $without = $middlewares
                                ->filter(
                                    fn(Middleware|WithoutMiddleware $middleware) => $middleware instanceof
                                        WithoutMiddleware
                                );
                            if ($without->isEmpty()) {
                                self::$compiled[$server][$class][$method] = $middlewares->all();
                            } else {
                                $excludes = $without
                                    ->map(fn(WithoutMiddleware $middleware) => $middleware->middleware)
                                    ->unique()->flip();
                                self::$compiled[$server][$class][$method] = $middlewares->filter(
                                    fn(Middleware|WithoutMiddleware $middleware) => ($middleware instanceof Middleware)
                                        && $excludes->has($middleware->middleware) === false
                                )->all();
                            }
                        });
                });
        }
        return self::$compiled[$server];
    }

    public static function middlewares(string $server,string $class,string $method) :array
    {
        return self::compile($server)[$class][$method] ?? [];
    }

    #[Pure]
    public static function instanceMiddleWare(string|array|Middleware|WithoutMiddleware $middleware) :Middleware|WithoutMiddleware
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