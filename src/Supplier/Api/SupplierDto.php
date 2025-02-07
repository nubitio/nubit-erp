<?php

namespace App\Supplier\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Supplier\Domain\Supplier;

class SupplierDto extends Supplier
{
    #[ApiProperty(identifier: true)]
    public ?string $proveedor_id;
}
