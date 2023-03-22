<?php

namespace App\{{module}}\Infrastructure;

{{imports}}

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "{{table}}")]
class {{module}}Entity
{
    public function __construct(
{{fields}}
    )
    {
    }
}