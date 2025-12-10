<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\AuthRefreshController;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;

#[ApiResource(
    shortName: 'Auth',
    description: 'Refresh access token using the refresh token cookie.',
    operations: [
        new Post(
            uriTemplate: '/auth/refresh',
            controller: AuthRefreshController::class,
            openapi: new OpenApiOperation(
                responses: [
                    '200' => ['description' => 'Access and refresh tokens rotated'],
                    '401' => ['description' => 'Invalid or expired refresh token'],
                ],
                summary: 'Refresh access token',
            ),
            input: false,
            read: false,
            deserialize: false,
            validate: false,
            write: false,
            name: 'auth_refresh'
        ),
    ],
    paginationEnabled: false
)]
final class AuthRefreshResource
{
}
