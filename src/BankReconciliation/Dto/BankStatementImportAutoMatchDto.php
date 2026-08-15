<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

class BankStatementImportAutoMatchDto
{
    public function __construct(
        public string $id,
    ) {}
}
