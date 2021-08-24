<?php

declare(strict_types=1);

namespace Weskiller\HyperfMiddleware\Middleware;

use Hyperf\Di\Annotation\AbstractAnnotation;
use Attribute;
use Hyperf\Di\Annotation\AnnotationInterface;

/**
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Expect implements AnnotationInterface
{
    public const Option = 'expect';

    use CollectTrait;

    public function __construct(public array $middlewares) {}

}