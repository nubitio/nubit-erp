<?php

namespace App\Config\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "options")]
class ConfigEntity
{

    public function __construct(
        #[Id]
        #[GeneratedValue]
        #[Column(name: "id", type: "integer")]
        public ?int $id = null,
        #[Column(name: "name", type: "string")]
        public ?string $name = null,
        #[Column(name: "value", type: "string")]
        public ?string $value = null
    )
    {
    }
}