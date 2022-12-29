<?php

namespace App\Billing\Model\Response;

class DocumentResponse
{
    public string $name;
    public string $xml;
    public string $hash;
    public SunatResponse $sunatResponse;
}
