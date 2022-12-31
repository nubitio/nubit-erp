<?php

namespace App\Core\Domain\Annotation;

/**
 * Annotation class for @IgnoreAutowire.
 *
 * @Annotation
 *
 * @Target({"CLASS"})
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class IgnoreAutowire
{
}
