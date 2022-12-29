<?php

namespace App\Billing\Model\Summary;

use App\Billing\Model\Company\Company;
use App\Util\DateUtils;

class Summary
{
    public string $correlativo;
    public \DateTimeInterface $fecGeneracion;
    public \DateTimeInterface $fecResumen;
    public string $moneda;
    public Company $company;
    /** @var SummaryDetail[] */
    public array $details;

    public function getXmlId(): string
    {
        $fecResumenPe = DateUtils::getDateWithTimeZone($this->fecResumen);
        $parts = [
            'RC',
            $fecResumenPe->format('Ymd'),
            $this->correlativo,
        ];

        return join('-', $parts);
    }

    public function getName(): string
    {
        return $this->company->ruc.'-'.$this->getXmlId();
    }
}
