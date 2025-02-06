<?php

namespace App\VoucherType\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\VoucherType\Domain\VoucherType;

class VoucherTypeDto extends VoucherType
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
