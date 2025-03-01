<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\Menu\MenuProvider;

#[ApiResource(
    uriTemplate: '/menu/{roleId}',
    shortName: 'Menu',
    operations: [new GetCollection()],
    uriVariables: [
        'roleId' => 'roleId',
    ],
    paginationEnabled: false,
    provider: MenuProvider::class
)]
class MenuResource
{
}
