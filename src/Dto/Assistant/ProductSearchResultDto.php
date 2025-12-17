<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

final class ProductSearchResultDto
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $code,
        public readonly string $name,
        public readonly float $salePrice,
        public readonly ?float $stock,
        public readonly ?float $minStock,
    ) {
    }
}
