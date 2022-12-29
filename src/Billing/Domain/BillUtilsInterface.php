<?php

namespace App\Billing\Domain;

use App\Billing\Model\Company\Company;

interface BillUtilsInterface
{
    public function getCompany(): Company;
}
