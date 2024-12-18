<?php

namespace App\Auth\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[ApiResource(
    uriTemplate: '/auth',
    shortName: 'Auth',
    operations: [
        new Post(
            status: 200,
            exceptionToStatus: [
                UserNotFoundException::class => 404,
                AuthenticationException::class => 401,
            ],
            input: AuthDto::class,
            output: TokenDto::class,
            processor: AuthProcessor::class,
        ),
    ]
)]
class AuthDto
{
    public string $username;
    public string $password;
    public ?string $branchId = null;
}
