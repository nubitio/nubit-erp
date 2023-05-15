<?php

namespace App\Billing\Domain;

use Greenter\Model\Company\Company;

interface BillUtilsInterface
{
    public function getCompany(): Company;
}
