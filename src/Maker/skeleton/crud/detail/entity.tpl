<?php

namespace App\{{module}}\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

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