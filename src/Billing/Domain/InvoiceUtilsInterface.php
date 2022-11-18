<?php

namespace App\Billing\Domain;

use App\Billing\Model\Company\Company;

interface InvoiceUtilsInterface
{
    public function getCompany(): Company;
}