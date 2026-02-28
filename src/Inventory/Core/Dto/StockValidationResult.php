<?php

declare(strict_types=1);

namespace App\Inventory\Core\Dto;

final readonly class StockValidationResult
{
    public function __construct(
        public bool $allowed,
        public ?string $warning = null,
        public ?string $reason = null,
    ) {
    }

    public static function allowed(): self
    {
        return new self(true);
    }

    public static function warning(string $message): self
    {
        return new self(true, $message, null);
    }

    public static function blocked(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
