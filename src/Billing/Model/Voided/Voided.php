<?php

namespace App\Billing\Model\Voided;

use App\Core\Domain\Util\DateUtils;
use Greenter\Model\Company\Company;

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
        return $this->company->getRuc().'-'.$this->getXmlId();
    }
}
