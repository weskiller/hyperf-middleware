<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Weskiller\HyperfMiddleware;

use Attribute;
use Hyperf\Di\Annotation\AbstractMultipleAnnotation;
use RuntimeException;

/**
 * @Annotation
 * @Target({"CLASS","METHOD"})
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware extends AbstractMultipleAnnotation
{
    protected static array $collect = [];

    public array $parameters = [];

    public function __construct(public string $middleware,...$parameters)
    {
        $this->parameters = $parameters;
    }

    public function collectClass(string $className): void
    {
        MiddlewareCollector::collectClass($className,$this);
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
        MiddlewareCollector::collectMethod($className,$target,$this);
    }

    public function collectProperty(string $className, ?string $target): void
    {
        /* middleware never defined on property, ignore  */
    }

}
