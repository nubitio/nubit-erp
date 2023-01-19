<?php

namespace App\{{module}}\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class {{module}}
{
    public function __construct(
{{fields}}
    )
    {
    }
}