<?php

namespace App\Employee\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Position\Infrastructure\PositionEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'employee')]
class EmployeeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\Column(name: 'id_number', type: 'string')]
        public ?string $id_number = null,
        #[ORM\Column(name: 'first_name', type: 'string')]
        public ?string $first_name = null,
        #[ORM\Column(name: 'last_name', type: 'string')]
        public ?string $last_name = null,
        #[ORM\Column(name: 'gender', type: 'integer')]
        public ?int $gender = null,
        #[ORM\Column(name: 'birthdate', type: 'date')]
        public ?\DateTimeInterface $birthdate = null,
        #[ORM\Column(name: 'address', type: 'string')]
        public ?string $address = null,
        #[ORM\Column(name: 'phone', type: 'string')]
        public ?string $phone = null,
        #[ORM\Column(name: 'hire_date', type: 'datetime')]
        public ?\DateTimeInterface $hire_date = null,
        #[ORM\ManyToOne(targetEntity: PositionEntity::class)]
        #[ORM\JoinColumn(name: 'position_id', referencedColumnName: 'id')]
        public ?PositionEntity $position = null,
        #[ORM\Column(name: 'salary', type: 'decimal')]
        public ?float $salary = null,
        #[ORM\Column(name: 'active', type: 'boolean')]
        public ?bool $active = null,
    ) {
    }

    public function getFullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
