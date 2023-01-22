<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Warehouse\Domain\Warehouse;

#[ApiResource(
    shortName: 'Warehouse',
    provider: WarehouseProvider::class,
    processor: WarehouseProcessor::class,
)]
class WarehouseDto extends Warehouse
{
    #[ApiProperty(identifier: true)]
    public ?string $almacen_id;
}