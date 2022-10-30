<?php

namespace App\Invoice\Model\Voided;

use App\Invoice\Model\Company\Company;

class Voided
{
    public string $correlativo;
    public string $fecGeneracion;
    public string $fecComunicacion;
    public Company $company;
    /** @var VoidedDetail[] */
    public array $details;
}