<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

interface UnitConversionServiceInterface
{
    public function toBase(string $productId, int $unitId, string $qty): string;

    public function fromBase(string $productId, int $unitId, string $qtyBase): string;
}
