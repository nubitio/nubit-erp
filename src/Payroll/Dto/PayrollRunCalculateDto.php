<?php

declare(strict_types=1);

namespace App\Payroll\Dto;

class PayrollRunCalculateDto
{
    public function __construct(
        public string $id,
    ) {}
}
