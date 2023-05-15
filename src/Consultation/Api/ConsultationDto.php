<?php

namespace App\Consultation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Consultation\Domain\Consultation;

#[ApiResource(
    shortName: 'Consultation',
    provider: ConsultationProvider::class,
    processor: ConsultationProcessor::class,
)]
class ConsultationDto extends Consultation
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
