<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Cashbox\Domain\Cashbox;

#[ApiResource(
    uriTemplate: '/cashboxes/{caja_id}/close',
    shortName: 'Cashbox',
    operations: [
        new Put(
            input: CloseCashboxDto::class,
            output: Cashbox::class,
            processor: CloseCashboxProcessor::class,
        ),
    ],
    uriVariables: [
        'caja_id' => new Link(
            fromProperty: 'caja_id',
            fromClass: Cashbox::class,
        ),
    ],
    provider: CashboxProvider::class
)]
class CloseCashboxDto
{
    public string $caja_id;
}
