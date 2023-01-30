<?php

namespace App\Auth\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    uriTemplate: '/auth',
    shortName: 'Auth',
    operations: [new Post(
        status: 200,
        input: AuthDto::class,
        output: TokenDto::class
    )]
)]
class AuthDto
{
    public string $username;
    public string $password;
}