<?php

declare(strict_types=1);

namespace App\ExpenseReports\Dto;

/** Shared input for both /approve and /reject — same shape, different target status. */
class ExpenseReportReviewDto
{
    public function __construct(
        public string $id,
        public ?string $note = null,
    ) {}
}
