<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Response\StatusResponse;
use App\Billing\Model\Summary\Summary;

interface SummaryRepositoryInterface
{
    public function getLastCorrelativeByDate(string $date): int;

    public function createSummary(Summary $summary): DocumentResponse;

    public function getStatus(string $ticket, string $ruc): StatusResponse;
}
