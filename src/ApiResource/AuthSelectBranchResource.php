<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Controller\AuthSelectBranchController;
use ArrayObject;

#[ApiResource(
    shortName: 'Auth',
    operations: [
        new Post(
            uriTemplate: '/auth/select-branch',
            controller: AuthSelectBranchController::class,
            openapi: new Operation(
                responses: [
                    '200' => new Response(
                        description: 'Tokens generados',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['type' => 'string'],
                                        'refreshToken' => ['type' => 'string'],
                                        'expiresAt' => ['type' => 'integer'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'username' => ['type' => 'string'],
                                                'userId' => ['type' => 'integer'],
                                                'role' => ['type' => 'integer'],
                                                'roles' => ['type' => 'array', 'items' => ['type' => 'string']],
                                            ],
                                        ],
                                        'branch' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'string'],
                                                'name' => ['type' => 'string', 'nullable' => true],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '400' => new Response(description: 'Sucursal no válida'),
                    '401' => new Response(description: 'No autenticado'),
                    '404' => new Response(description: 'Sucursal no encontrada'),
                ],
                summary: 'Seleccionar sucursal y generar tokens con contexto',
                description: 'Genera un nuevo par de tokens (access/refresh) con la sucursal seleccionada.',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['branchId'],
                                'properties' => [
                                    'branchId' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ]),
                    required: true
                )
            ),
            security: "is_granted('ROLE_USER')",
            read: false,
        ),
    ],
)]
final class AuthSelectBranchResource
{
    public string $branchId;
}
