<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Response\SummaryResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Voided\Voided;

interface VoidedRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function voidInvoice(Voided $voided): SummaryResponse;

    public function getStatus(string $ticket, string $ruc): StatusCdrResult;
}
