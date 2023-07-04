<?php

namespace App\Customer\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Customer\Domain\Customer;

#[ApiResource(
    shortName: 'Customer',
    provider: CustomerProvider::class,
    processor: CustomerProcessor::class,
)]
class CustomerDto extends Customer
{
    #[ApiProperty(identifier: true)]
    public ?int $cliente_id;
}
