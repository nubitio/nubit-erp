<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\RoleMenu\MenuDto;
use App\State\RoleMenu\RoleMenuProcessor;
use App\State\RoleMenu\RoleMenuProvider;

#[ApiResource(
    shortName: 'RoleMenu',
    operations: [
        new GetCollection(
            uriTemplate: '/role-menu/{role_id}',
            output: MenuDto::class,
            provider: RoleMenuProvider::class,
        ),
        new Post(
            uriTemplate: '/role-menu',
            input: RoleMenuResource::class,
            processor: RoleMenuProcessor::class,
        ),
    ],
    paginationEnabled: false
)]
class RoleMenuResource
{
    public function __construct(
        public int $roleId,
        /**
         * @var int[]
         */
        public array $permissions,
    ) {
    }
}
