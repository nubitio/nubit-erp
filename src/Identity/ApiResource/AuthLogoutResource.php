<?php

declare(strict_types=1);

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Identity\Controller\AuthLogoutController;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;

#[ApiResource(
    shortName: 'Auth',
    description: 'Revoke refresh token and clear authentication cookies.',
    operations: [
        new Post(
            uriTemplate: '/auth/logout',
            controller: AuthLogoutController::class,
            openapi: new OpenApiOperation(
                responses: [
                    200 => new OpenApiResponse(description: 'Logout successful'),
                    401 => new OpenApiResponse(description: 'Unauthorized'),
                ],
                summary: 'Logout and revoke refresh token',
            ),
            input: false,
            read: false,
            deserialize: false,
            validate: false,
            write: false,
            name: 'auth_logout'
        ),
    ],
    paginationEnabled: false
)]
final class AuthLogoutResource
{
}
