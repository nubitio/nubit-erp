<?php

declare(strict_types=1);

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Identity\Controller\AuthChangePasswordController;
use ArrayObject;

#[ApiResource(
    shortName: 'Auth',
    description: 'Change the authenticated user\'s own password.',
    operations: [
        new Post(
            uriTemplate: '/auth/change-password',
            controller: AuthChangePasswordController::class,
            openapi: new OpenApiOperation(
                responses: [
                    200 => new OpenApiResponse(description: 'Password changed successfully'),
                    400 => new OpenApiResponse(description: 'Current password is incorrect'),
                    401 => new OpenApiResponse(description: 'Unauthorized'),
                    422 => new OpenApiResponse(description: 'Validation error'),
                ],
                summary: 'Change own password',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['currentPassword', 'newPassword'],
                                'properties' => [
                                    'currentPassword' => ['type' => 'string'],
                                    'newPassword'     => ['type' => 'string', 'minLength' => 6],
                                ],
                            ],
                        ],
                    ])
                ),
            ),
            input: false,
            read: false,
            deserialize: false,
            validate: false,
            write: false,
            name: 'auth_change_password',
        ),
    ],
    paginationEnabled: false,
)]
final class AuthChangePasswordResource
{
}
