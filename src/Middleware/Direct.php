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
class Direct implements AnnotationInterface
{
    public function __construct() {}

    public const Option = 'without';

    use CollectTrait;
}