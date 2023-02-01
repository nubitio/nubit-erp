<?php

namespace App\Supplier\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Supplier\Domain\Supplier;

#[ApiResource(
    shortName: 'Supplier',
    provider: SupplierProvider::class,
    processor: SupplierProcessor::class,
)]
class SupplierDto extends Supplier
{
    #[ApiProperty(identifier: true)]
    public ?string $proveedor_id;
}