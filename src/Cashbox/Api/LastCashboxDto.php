<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Cashbox\Domain\Cashbox;
use App\Entity\Branch;

#[ApiResource(
    uriTemplate: '/cashboxes/{branch_id}/last',
    shortName: 'Cashbox',
    operations: [new Get()],
    uriVariables: [
        'branch_id' => new Link(
            fromProperty: 'local_id',
            fromClass: Branch::class,
        ),
    ],
    provider: LastCashboxProvider::class
)]
class LastCashboxDto extends Cashbox
{
}
