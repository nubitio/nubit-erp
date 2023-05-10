<?php

namespace App\Consultation\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Patient\Infrastructure\PatientEntity;
use App\Physician\Infrastructure\PhysicianEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "consultation")]
class ConsultationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int                $id = null,
        #[ORM\ManyToOne(targetEntity: PatientEntity::class)]
        #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'id')]
        public ?PatientEntity      $patient = null,
        #[ORM\ManyToOne(targetEntity: PhysicianEntity::class)]
        #[ORM\JoinColumn(name: 'physician_id', referencedColumnName: 'id')]
        public ?PhysicianEntity    $physician = null,
        #[ORM\Column(name: 'date', type: 'date')]
        public ?\DateTimeInterface $date = null,
        #[ORM\Column(name: 'reason', type: 'string')]
        public ?string             $reason = null,
        #[ORM\Column(name: 'diagnosis', type: 'string')]
        public ?string             $diagnosis = null,
        #[ORM\Column(name: 'treatment', type: 'string')]
        public ?string             $treatment = null,
        #[ORM\Column(name: 'notes', type: 'text')]
        public ?string             $notes = null,
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[ORM\Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null
    )
    {
    }
}