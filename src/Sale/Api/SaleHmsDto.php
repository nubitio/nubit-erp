<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Physician\Domain\Physician;
use App\Sale\Domain\Sale;

#[ApiResource(
    shortName: 'SaleHms',
    provider: SaleProvider::class,
    processor: SaleProcessor::class,
)]
class SaleHmsDto extends Sale
{
    #[ApiProperty(identifier: true)]
    public ?string $movimiento_id;

    public ?Physician $physician;
}
