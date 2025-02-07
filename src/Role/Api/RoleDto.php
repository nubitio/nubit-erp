<?php

namespace App\Role\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Role\Domain\Role;

class RoleDto extends Role
{
    #[ApiProperty(identifier: true)]
    public ?int $rol_id;
}
