<?php

namespace App\Invoice\Domain;

use App\Invoice\Model\Company\Company;

interface InvoiceUtilsInterface
{
    public function getCompany(): Company;
}