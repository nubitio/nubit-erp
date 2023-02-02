<?php

namespace App\Role\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "rol")]
class RoleEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'rol_id', type: 'integer')]
        public ?int $rol_id = null,
        #[ORM\Column(name: 'descripcion', type: 'string')]
        public ?string $descripcion = null
    )
    {
    }
}