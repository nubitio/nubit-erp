<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\SummaryResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Summary\Summary;

interface SummaryRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function createSummary(Summary $summary): SummaryResponse;

    public function getStatus(string $ticket, string $ruc): StatusCdrResult;
}
