<?php

namespace App\Consultation\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Patient\Domain\Patient;
use App\Physician\Domain\Physician;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Consultation
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('patient')]
        public ?Patient $patient = null,
        #[SerializedName('physician')]
        public ?Physician $physician = null,
        #[SerializedName('date')]
        public ?\DateTimeInterface $date = null,
        #[SerializedName('reason')]
        public ?string $reason = null,
        #[SerializedName('diagnosis')]
        public ?string $diagnosis = null,
        #[SerializedName('treatment')]
        public ?string $treatment = null,
        #[SerializedName('notes')]
        public ?string $notes = null,
        #[SerializedName('queue')]
        public ?int $queue = null,
        #[SerializedName('created_at')]
        public ?\DateTimeInterface $created_at = null,
        #[SerializedName('updated_at')]
        public ?\DateTimeInterface $updated_at = null
    ) {
    }
}
