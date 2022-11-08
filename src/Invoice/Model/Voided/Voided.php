<?php

namespace App\Invoice\Model\Voided;

use App\Invoice\Model\Company\Company;

class Voided
{
    public string $correlativo;
    public \DateTimeInterface $fecGeneracion;
    public \DateTimeInterface $fecComunicacion;
    public Company $company;
    /** @var VoidedDetail[] */
    public array $details;
}