<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

class BankStatementLineUnmatchDto
{
    public function __construct(
        public int|string $id,
    ) {}
}
