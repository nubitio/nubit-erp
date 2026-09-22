<?php

declare(strict_types=1);

namespace App\Settings\Dto;

final readonly class TenantWarehouseView
{
    public function __construct(
        public ?string $id,
        public string $name,
    ) {}
}
