<?php

declare(strict_types=1);

namespace App\Payroll\Dto;

class PayrollRunPayDto
{
    public function __construct(
        public string $id,
    ) {}
}
