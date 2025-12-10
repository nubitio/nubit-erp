<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\AuthLogoutController;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;

#[ApiResource(
    shortName: 'Auth',
    description: 'Revoke refresh token and clear authentication cookies.',
    operations: [
        new Post(
            uriTemplate: '/auth/logout',
            routeName: 'auth_logout',
            controller: AuthLogoutController::class,
            openapi: new OpenApiOperation(
                responses: [
                    '200' => ['description' => 'Logout successful'],
                    '401' => ['description' => 'Unauthorized'],
                ],
                summary: 'Logout and revoke refresh token',
            ),
            read: false,
            write: false,
            name: 'auth_logout'
        ),
    ],
    paginationEnabled: false
)]
final class AuthLogoutResource
{
}
