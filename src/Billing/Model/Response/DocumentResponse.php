<?php

namespace App\Billing\Model\Response;

use Greenter\Model\Response\BillResult;

class DocumentResponse
{
    public string $name;
    public string $xml;
    public string $hash;
    public BillResult $sunatResponse;
}
