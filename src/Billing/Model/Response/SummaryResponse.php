<?php

declare(strict_types=1);

namespace App\Billing\Model\Response;

use Greenter\Model\Response\SummaryResult;

class SummaryResponse
{
    public string $name;

    public string $xml;

    public string $hash;

    public SummaryResult $sunatResponse;
}
