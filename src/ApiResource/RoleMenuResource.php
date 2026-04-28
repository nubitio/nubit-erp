<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Settings\Dto\RoleMenuDto;
use App\Settings\State\RoleMenuProcessor;
use App\Settings\State\RoleMenuProvider;

#[ApiResource(
    shortName: 'RoleMenu',
    operations: [
        new GetCollection(
            uriTemplate: '/role-menu/{role_id}',
            output: RoleMenuDto::class,
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
