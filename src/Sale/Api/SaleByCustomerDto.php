<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Customer\Domain\Customer;
use App\Sale\Domain\Sale;

#[ApiResource(
    uriTemplate: '/customers/{customer_id}/sales',
    shortName: 'Sale',
    operations: [new GetCollection()],
    uriVariables: [
        'customer_id' => new Link(
            fromProperty: 'ruc',
            fromClass: Customer::class,
        ),
    ],
    provider: SaleByCustomerProvider::class
)]
class SaleByCustomerDto extends Sale
{
}
