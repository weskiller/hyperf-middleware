<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware;

use Hyperf\Di\MetadataCollectorInterface;
use Hyperf\Utils\Arr;

class MiddlewareCollector implements MetadataCollectorInterface
{
    protected static array $collect = [
        'class' => [],
        'method' => [],
    ];

    public static function get(string $key, $default = null)
    {
        return Arr::get(static::$collect,$key,$default);
    }

    public static function set(string $key, $value): void
    {
        Arr::set(static::$collect,$key,$value);
    }

    public static function clear(?string $key = null): void
    {
        Arr::forget(static::$collect,$key);
    }

    public static function serialize(): string
    {
        return serialize(static::$collect);
    }

    public static function deserialize(string $metadata): bool
    {
        static::$collect = unserialize($metadata,['allowed_classes' => [Middleware::class]]);
        return true;
    }

    public static function list(): array
    {
        return static::$collect;
    }

    /**
     * @param string $class
     * @param Middleware $middleware
     */
    public static function collectClass(string $class,Middleware $middleware): void
    {
        static::$collect['class'][$class][] = $middleware;
    }

    /**
     * @param string $class
     * @param string $target
     * @param Middleware $middleware
     */
    public static function collectMethod(string $class, string $target,Middleware $middleware): void
    {
        static::$collect['method'][$class][$target][] = $middleware;
    }

    public static function getClass(string $class) :array
    {
        return (array) Arr::get(static::$collect['class'],$class);
    }

    public static function getMethod(string $class,string $target) :array
    {
        return (array) Arr::get(static::$collect['method'],$class.'.'.$target);
    }
}