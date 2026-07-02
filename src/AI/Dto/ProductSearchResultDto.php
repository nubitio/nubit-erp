<?php

declare(strict_types=1);

namespace App\AI\Dto;

final readonly class ProductSearchResultDto
{
    public function __construct(
        public string $id,
        public ?string $code,
        public string $name,
        public float $salePrice,
        public ?float $stock,
        public ?float $minStock,
    ) {}
}
