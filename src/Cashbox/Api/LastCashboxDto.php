<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Branch\Infrastructure\BranchEntity;
use App\Cashbox\Domain\Cashbox;

#[ApiResource(
    uriTemplate: '/cashboxes/{branch_id}/last',
    shortName: 'Cashbox',
    operations: [new Get()],
    uriVariables: [
        'branch_id' => new Link(
            fromProperty: 'local_id',
            fromClass: BranchEntity::class,
        ),
    ],
    provider: LastCashboxProvider::class
)]
class LastCashboxDto extends Cashbox
{
}
