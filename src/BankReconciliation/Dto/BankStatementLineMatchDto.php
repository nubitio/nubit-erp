<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

class BankStatementLineMatchDto
{
    public function __construct(
        public string $id,
        public string $sourceType,
        public int|string $sourceId,
    ) {}
}
