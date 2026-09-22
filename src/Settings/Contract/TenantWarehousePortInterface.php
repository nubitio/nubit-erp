<?php

declare(strict_types=1);

namespace App\Settings\Contract;

interface TenantWarehousePortInterface
{
    public function count(): int;

    public function hasBranch(): bool;

    public function createDefault(string $name): void;
}
