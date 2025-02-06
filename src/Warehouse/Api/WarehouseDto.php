<?php

namespace App\Warehouse\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Warehouse\Domain\Warehouse;

class WarehouseDto extends Warehouse
{
    #[ApiProperty(identifier: true)]
    public ?string $almacen_id;
}
