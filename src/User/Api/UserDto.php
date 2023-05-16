<?php

namespace App\User\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\User\Domain\User;

#[ApiResource(
    shortName: 'User',
    provider: UserProvider::class,
    processor: UserProcessor::class,
)]
class UserDto extends User
{
    #[ApiProperty(identifier: true)]
    public ?int $usuario_id;
}
