<?php

namespace App\Billing\Model\Summary;

use App\Core\Domain\Util\DateUtils;
use Greenter\Model\Company\Company;

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
        return $this->company->getRuc().'-'.$this->getXmlId();
    }
}
