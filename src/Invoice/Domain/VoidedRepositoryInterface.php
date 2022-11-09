<?php

namespace App\Invoice\Domain;

use App\Invoice\Model\Voided\Response\VoidedResponse;
use App\Invoice\Model\Voided\Voided;

interface VoidedRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function voidInvoice(Voided $voided): VoidedResponse;
}