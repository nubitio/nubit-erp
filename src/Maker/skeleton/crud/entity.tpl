<?php

namespace App\{{module}}\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\{{module}}\Domain\{{module}};
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "{{table}}")]
class {{module}}Entity
{

    public function __construct(
{{fields}}
    )
    {
    }

    public function mapToDomainModel(): {{module}}
    {
        return new {{module}}(
{{mapToDomainModelFields}}
        );
    }

    public static function mapFromDomainModel({{module}} ${{camelModule}}): {{module}}Entity
    {
        return self(
{{mapFromDomainModelFields}}
        );
    }
}