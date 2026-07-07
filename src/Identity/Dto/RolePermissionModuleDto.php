<?php

declare(strict_types=1);

namespace App\Identity\Dto;

final readonly class RolePermissionModuleDto
{
    public function __construct(
        public string $key,
        public string $label,
        /** @var list<RolePermissionItemDto> */
        public array $permissions,
    ) {}
}
