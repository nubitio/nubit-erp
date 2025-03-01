<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Sale\Domain\Sale;

#[ApiResource(
    shortName: 'Sale',
    provider: SaleProvider::class,
    processor: SaleProcessor::class,
)]
class SaleDto extends Sale
{
    #[ApiProperty(identifier: true)]
    public ?string $movimiento_id = null;
}
