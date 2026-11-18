<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

final readonly class TreasuryAccount
{
    public function __construct(
        public string $id,
        public AccountKind $kind,
        public string $name,
        public string $currency,
        public ?string $branchId,
        public ?int $cashRegisterId,
        public ?string $bankAccountId,
        public bool $active,
    ) {}
}
