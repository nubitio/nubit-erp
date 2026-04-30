<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Model\Response;

use Greenter\Model\Response\BillResult;

class DocumentResponse
{
    public string $name;

    public string $xml;

    public string $hash;

    public BillResult $sunatResponse;
}
