<?php

declare(strict_types=1);

namespace App\Efact\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class IgnoreAutowire {}
