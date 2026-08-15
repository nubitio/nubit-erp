<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

class BankStatementLineMatchDto
{
    public function __construct(
        public int|string $id,
        public string $sourceType,
        public int|string $sourceId,
    ) {}
}
