<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

class BankStatementLineIgnoreDto
{
    public function __construct(
        public int|string $id,
    ) {}
}
