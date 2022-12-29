<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Response\StatusResponse;
use App\Billing\Model\Voided\Voided;

interface VoidedRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function voidInvoice(Voided $voided): DocumentResponse;

    public function getStatus(string $ticket, string $ruc): StatusResponse;
}
