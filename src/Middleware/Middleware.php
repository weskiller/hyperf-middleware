<?php

declare(strict_types=1);

namespace Weskiller\HyperfMiddleware\Middleware;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Di\Annotation\AbstractMultipleAnnotation;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\AnnotationInterface;
use RuntimeException;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware implements AnnotationInterface
{
    public const Option = 'middleware';
    
    public const MultipleOption = 'middlewares';

    protected static array $collect = [];

    use CollectTrait;

    public array $parameters = [];

    public function __construct(public string $middleware,...$parameters)
    {
        $this->parameters = $parameters;
    }

}
