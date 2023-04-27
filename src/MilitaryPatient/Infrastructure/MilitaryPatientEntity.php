<?php

namespace App\MilitaryPatient\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Patient\Infrastructure\PatientEntity;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "military_patient")]
class MilitaryPatientEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'id', type: 'integer')]
        public ?int           $id = null,
        #[ORM\ManyToOne(targetEntity: PatientEntity::class)]
        #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'id')]
        public ?PatientEntity $patient = null,
        #[ORM\Column(name: 'cip_code', type: 'string')]
        public ?string        $cip_code = null,
        #[ORM\Column(name: 'rank', type: 'string')]
        public ?string        $rank = null,
        #[ORM\Column(name: 'weapon_code', type: 'string')]
        public ?string        $weapon_code = null
    )
    {
    }
}