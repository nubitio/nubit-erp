<?php

namespace App\Menu\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    uriTemplate: '/menu/{role_id}',
    shortName: 'MenuDto',
    operations: [new GetCollection()],
    uriVariables: [
        'role_id' => 'role_id',
    ],
    paginationEnabled: false,
    provider: MenuProvider::class
)]
class MenuDTO
{
    public function __construct(
        public string $text,
        public string $icon,
        public string $path,
        /** @var array<int, SubMenu> */
        public array $items = [],
    ) {
    }
}
