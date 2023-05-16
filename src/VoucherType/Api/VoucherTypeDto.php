<?php

namespace App\VoucherType\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\VoucherType\Domain\VoucherType;

#[ApiResource(
    shortName: 'VoucherType',
    provider: VoucherTypeProvider::class,
    processor: VoucherTypeProcessor::class,
)]
class VoucherTypeDto extends VoucherType
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
