<?php

declare(strict_types=1);

namespace App\ExpenseReports\Dto;

class ExpenseReportReimburseDto
{
    public function __construct(
        public string $id,
    ) {}
}
