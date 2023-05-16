<?php

namespace App\ConsultationSale\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\ConsultationSale\Domain\ConsultationSale;

#[ApiResource(
    shortName: 'ConsultationSale',
    provider: ConsultationSaleProvider::class,
    processor: ConsultationSaleProcessor::class,
)]
class ConsultationSaleDto extends ConsultationSale
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
