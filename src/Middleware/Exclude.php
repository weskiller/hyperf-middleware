<?php

declare(strict_types=1);

namespace Weskiller\HyperfMiddleware\Middleware;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Di\Annotation\AbstractMultipleAnnotation;
use Hyperf\Di\Annotation\AnnotationInterface;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Exclude implements AnnotationInterface
{
    public const Option = 'exclude';

    use CollectTrait;

    public function __construct(public array $middlewares) {}
}
