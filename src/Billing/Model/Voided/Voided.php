<?php

namespace App\Billing\Model\Voided;

use App\Billing\Model\Company\Company;
use App\Util\DateUtils;

class Voided
{
    public string $correlativo;
    public \DateTimeInterface $fecGeneracion;
    public \DateTimeInterface $fecComunicacion;
    public Company $company;
    /** @var VoidedDetail[] */
    public array $details;

    public function getXmlId(): string
    {
        $date = DateUtils::getDateWithTimeZone($this->fecComunicacion);
        $parts = [
            'RA',
            $date->format('Ymd'),
            $this->correlativo,
        ];

        return join('-', $parts);
    }

    public function getName(): string
    {
        return $this->company->ruc . '-' . $this->getXmlId();
    }
}