<?php

namespace App\Invoice\Model\Voided;

use App\Invoice\Model\Company\Company;

class Voided
{
    public string $correlativo;
    public \DateTime $fecGeneracion;
    public \DateTime $fecComunicacion;
    public Company $company;
    /** @var VoidedDetail[] */
    public array $details;
}