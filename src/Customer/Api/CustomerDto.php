<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Customer\Domain\Customer;

class CustomerDto extends Customer
{
    #[ApiProperty(identifier: true)]
    public ?string $ruc;
}
