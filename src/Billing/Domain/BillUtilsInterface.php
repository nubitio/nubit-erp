<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use Greenter\Model\Company\Company;

interface BillUtilsInterface
{
    public function getCompany(): Company;
}
