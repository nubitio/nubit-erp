<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Branch\Api\BranchDto;
use App\Warehouse\Domain\Warehouse;

#[ApiResource(
    shortName: 'Warehouse',
    provider: WarehouseProvider::class,
    processor: WarehouseProcessor::class,
)]
#[ApiResource(
    uriTemplate: '/branches/{local_id}/warehouses',
    shortName: 'Warehouse',
    operations: [new GetCollection()],
    uriVariables: [
        'local_id' => new Link(
            fromProperty: 'local_id',
            fromClass: BranchDto::class,
        ),
    ],
    provider: WarehouseProvider::class
)]
class WarehouseDto extends Warehouse
{
    #[ApiProperty(identifier: true)]
    public ?string $almacen_id;
}
