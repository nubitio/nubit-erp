<?php

declare(strict_types=1);

namespace App\Identity\Dto;

final readonly class RolePermissionItemDto
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $granted,
    ) {}
}
