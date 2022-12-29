<?php

namespace App\Billing\Model\Client;

use App\Billing\Model\Address\Address;

class Client
{
    public string $tipoDoc;
    public string $numDoc;
    public string $rznSocial;
    public Address $address;
}
