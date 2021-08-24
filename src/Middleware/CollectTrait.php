<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Middleware;


use RuntimeException;

/**
 * @mixin Middleware
 */
trait CollectTrait
{
    public function collectClass(string $className): void
    {
        Collector::collectClass($className,$this);
    }

    /**
     * @param string $className
     * @param string|null $target
     */
    public function collectMethod(string $className, ?string $target): void
    {
        if($target === null) {
            throw new RuntimeException(sprintf('collect %s method %s error',$className,$target));
        }
        Collector::collectMethod($className,$target,$this);
    }

    public function collectProperty(string $className, ?string $target): void
    {
        throw new RuntimeException(sprintf('the middleware cannot declare in the attribute in %s',$className));
    }
}