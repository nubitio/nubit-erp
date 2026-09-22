<?php

declare(strict_types=1);

namespace App\Settings\Contract;

use App\Settings\Dto\TenantWarehouseView;

interface TenantWarehousePortInterface
{
    public function count(): int;

    public function defaultWarehouse(): ?TenantWarehouseView;

    public function hasBranch(): bool;

    public function createDefault(string $name): void;
}
