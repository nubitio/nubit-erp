<?php

namespace App\Role\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Role\Domain\Role;

#[ApiResource(
    shortName: 'Role',
    provider: RoleProvider::class,
    processor: RoleProcessor::class,
)]
class RoleDto extends Role
{
    #[ApiProperty(identifier: true)]
    public ?int $rol_id;
}
