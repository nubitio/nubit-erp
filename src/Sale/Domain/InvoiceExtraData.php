<?php

namespace App\Sale\Domain;

class InvoiceExtraData
{
    public function __construct(
        public ?string $cipCode = null,
        public ?string $rank = null,
        public ?string $weaponCode = null,
        public ?string $medicalRecordNumber = null,
        public ?string $physicianFullName = null,
    ) {
    }
}
