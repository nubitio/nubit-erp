<?php

declare(strict_types=1);

namespace App\Inventory\Core\Dto;

final readonly class StockAuthorizationResult
{
    private function __construct(
        public bool $granted,
        public ?string $reason = null,
    ) {
    }

    public static function granted(): self
    {
        return new self(true);
    }

    public static function denied(string $reason): self
    {
        return new self(false, $reason);
    }
}
