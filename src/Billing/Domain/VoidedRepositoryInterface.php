<?php

namespace App\Billing\Domain;

use App\Billing\Model\Voided\Response\VoidedResponse;
use App\Billing\Model\Voided\Voided;

interface VoidedRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function voidInvoice(Voided $voided): VoidedResponse;
}