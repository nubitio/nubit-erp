<?php

namespace App\Billing\Model\Summary;

use App\Billing\Model\Company\Company;

class Summary
{
    public string $correlativo;
    public \DateTimeInterface $fecGeneracion;
    public \DateTimeInterface $fecResumen;
    public string $moneda;
    public Company $company;
    /** @var SummaryDetail[] */
    public array $details;
}