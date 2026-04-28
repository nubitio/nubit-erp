<?php

declare(strict_types=1);

namespace App\Identity\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Identity\Controller\AuthSelectBranchController;
use App\Identity\Dto\AuthResponseDto;

#[ApiResource(
    shortName: 'Auth',
    operations: [
        new Post(
            uriTemplate: '/auth/select-branch',
            controller: AuthSelectBranchController::class,
            openapi: new Operation(
                summary: 'Seleccionar sucursal y generar tokens con contexto',
                description: 'Genera un nuevo par de tokens (access/refresh) con la sucursal seleccionada.',
            ),
            security: "is_granted('ROLE_USER')",
            input: AuthSelectBranchResource::class,
            output: AuthResponseDto::class,
            read: false,
        ),
    ],
)]
final class AuthSelectBranchResource
{
    public string $branchId;
}
