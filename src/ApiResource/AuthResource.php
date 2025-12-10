<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\AuthController;
use App\Dto\Auth\TokenDto;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[ApiResource(
    shortName: 'Auth',
    operations: [
        new Post(
            uriTemplate: '/auth/login',
            status: 200,
            controller: AuthController::class,
            exceptionToStatus: [
                UserNotFoundException::class => 404,
                AuthenticationException::class => 401,
            ],
            input: AuthResource::class,
            output: TokenDto::class,
            name: 'auth_login'
        ),
    ]
)]
class AuthResource
{
    public string $username;

    public string $password;

    public ?string $branchId = null;
}
