<?php

namespace App\Billing\Model\Voided\Response;

class VoidedResponse
{
    public string $name;
    public string $xml;
    public string $hash;
    public SunatResponse $sunatResponse;
}