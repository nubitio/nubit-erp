<?php

namespace App\User\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\User\Domain\User;

class UserDto extends User
{
    #[ApiProperty(identifier: true)]
    public ?int $usuario_id;
}
