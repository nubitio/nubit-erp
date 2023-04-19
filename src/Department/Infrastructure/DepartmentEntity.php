<?php

namespace App\Department\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "department")]
class DepartmentEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'name', type: 'string')]
        public ?string $name = null,
        #[ORM\Column(name: 'description', type: 'string')]
        public ?string $description = null
    )
    {
    }
}