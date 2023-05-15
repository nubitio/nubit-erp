<?php

namespace App\Physician\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Employee\Infrastructure\EmployeeEntity;
use App\Specialty\Infrastructure\SpecialtyEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'physician')]
class PhysicianEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: EmployeeEntity::class)]
        #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id')]
        public ?EmployeeEntity $employee = null,
        #[ORM\ManyToOne(targetEntity: SpecialtyEntity::class)]
        #[ORM\JoinColumn(name: 'specialty_id', referencedColumnName: 'id')]
        public ?SpecialtyEntity $specialty = null,
        #[ORM\Column(name: 'licence', type: 'string')]
        public ?string $licence = null
    ) {
    }

    public function getFullName(): ?string
    {
        return $this->employee?->getFullName();
    }
}
