<?php

namespace App\Position\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Department\Infrastructure\DepartmentEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'position')]
class PositionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: DepartmentEntity::class)]
        #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id')]
        public ?DepartmentEntity $department = null,
        #[ORM\Column(name: 'name', type: 'string')]
        public ?string $name = null,
        #[ORM\Column(name: 'description', type: 'string')]
        public ?string $description = null
    ) {
    }
}
