<?php

namespace App\Patient\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Infrastructure\CustomerEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "patient")]
class PatientEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int $id = null,
        #[ORM\ManyToOne(targetEntity: CustomerEntity::class)]
        #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'ruc')]
        public ?CustomerEntity $customer = null,
        #[ORM\Column(name: 'type_id', type: 'integer')]
        public ?int $type_id = null,
        #[ORM\Column(name: 'insurance_provider', type: 'string')]
        public ?string $insurance_provider = null,
        #[ORM\Column(name: 'medical_record_number', type: 'string')]
        public ?string $medical_record_number = null,
        #[ORM\Column(name: 'created_by', type: 'integer')]
        public ?int $created_by = null,
        #[ORM\Column(name: 'updated_by', type: 'integer')]
        public ?int $updated_by = null,
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[ORM\Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null
    )
    {
    }
}