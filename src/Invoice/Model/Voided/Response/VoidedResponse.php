<?php

namespace App\Invoice\Model\Voided\Response;

class VoidedResponse
{
    public string $xml;
    public string $hash;
    public SunatResponse $sunatResponse;
}