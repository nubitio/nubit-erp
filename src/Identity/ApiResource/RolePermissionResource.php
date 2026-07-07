<?php

declare(strict_types=1);

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Identity\Dto\RolePermissionModuleDto;
use App\Identity\State\RolePermissionProcessor;
use App\Identity\State\RolePermissionProvider;

#[ApiResource(
    shortName: 'RolePermission',
    operations: [
        new GetCollection(
            uriTemplate: '/role-permissions/{role_id}',
            security: "is_granted('ROLE_ADMIN')",
            output: RolePermissionModuleDto::class,
            provider: RolePermissionProvider::class,
        ),
        new Post(
            uriTemplate: '/role-permissions',
            security: "is_granted('ROLE_ADMIN')",
            input: RolePermissionResource::class,
            processor: RolePermissionProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
class RolePermissionResource
{
    public function __construct(
        public int $roleId,
        /**
         * @var string[]
         */
        public array $permissions,
    ) {}
}
