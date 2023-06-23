<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\SummaryResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Voided\Voided;

interface VoidedRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function voidInvoice(Voided $voided): SummaryResponse;

    public function getStatus(string $ticket, string $ruc): StatusCdrResult;
}
