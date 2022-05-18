<?php

namespace App\Domain\Annotation;

use Attribute;

/**
 * Annotation class for @IgnoreAutowire
 * @Annotation
 * @Target({"CLASS"})
 */
#[Attribute(Attribute::TARGET_CLASS)]
class IgnoreAutowire
{
}