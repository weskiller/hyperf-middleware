<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Middleware;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\MetadataCollectorInterface;
use Hyperf\Utils\ApplicationContext;
use JetBrains\PhpStorm\Pure;

class Collector implements MetadataCollectorInterface
{
    protected static array $global = [];
    protected static array $collect = [ 'c' => [],'m' => [] ];
    protected static array $routes = [];

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

    public static function list() :array
    {
        return self::$collect;
    }

    public static function serialize(): string
    {
        return serialize(self::$collect);
    }

    public static function deserialize(string $metadata): bool
    {
        static::$collect = unserialize($metadata,['allowed_classes' => [Middleware::class,Exclude::class,Expect::class,Direct::class]]);
        return true;
    }

    /**
     * @param string $class
     * @param Middleware|Exclude|Expect|Direct $middleware
     */
    public static function collectClass(string $class,  Middleware|Exclude|Expect|Direct $middleware): void
    {
        static::$collect['c'][$class][] = $middleware;
    }

    /**
     * @param string $class
     *
     * @param string $method
     * @param Middleware|Exclude|Expect|Direct $middleware
     */
    public static function collectMethod(string $class, string $method, Middleware|Exclude|Expect|Direct $middleware): void
    {
        self::$collect['m'][$class][$method][] = $middleware;
    }

    public static function getMethodMiddlewares(string $class,string $method) :array
    {
        return self::$collect['m'][$class][$method] ?? [];
    }

    public static function getClassMiddlewares(string $class) :array
    {
        return self::$collect['c'][$class] ?? [];
    }

    public static function getGlobalMiddlewares(string $server) :array
    {
        if(!isset(self::$global[$server])) {
            self::$global[$server] = array_map(
                [static::class,'instanceMiddleWare'],
                (array) ApplicationContext::getContainer()->get(ConfigInterface::class)->get('middlewares.' . $server)
            );
        }
        return self::$global[$server];
    }

    public static function addRouteMiddlewares(string $server,string $route,string $routeMethod,array $middlewares) :void
    {
        self::$routes[$server][$route][$routeMethod] = self::compose($middlewares);
    }

    /**
     * @param  Middleware[]|Exclude[]|Expect[]|Direct[] $middlewares
     */
    public static function compose(array $middlewares) :array
    {
        $needs = collect();
        $excludes = collect();
        $expects = collect();
        foreach ($middlewares as $middleware) {
            switch ($middleware::class) {
                case Middleware::class:
                    $needs[] = $middleware;
                    break;
                case Exclude::class:
                    collect($middleware->middlewares)->map(fn(string $name) => $excludes->offsetSet($name,true));
                    break;
                case Expect::class:
                    collect($middleware->middlewares)->map(fn(string $name) => $expects->offsetSet($name,true));
                    break;
                case Direct::class:
                    return [];
            }
        }
        if($needs->isEmpty()) {
            return [];
        }
        if($excludes->isNotEmpty()) {
            $needs = $needs->filter(fn(Middleware $need) => !$excludes->has($need->middleware));
        }
        if($expects->isNotEmpty()) {
            $needs = $needs->filter(fn(Middleware $need) => $expects->has($need->middleware));
        }
        return $needs->all();
    }

    public static function getRouteMiddlewares(string $server,string $method,string $route) :array
    {
        return self::$routes[$server][$method][$route] ?? [];
    }

    #[Pure]
    public static function instanceMiddleWare(string|array|Middleware|Exclude|Expect|Direct $middleware) :Middleware|Exclude|Expect|Direct
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