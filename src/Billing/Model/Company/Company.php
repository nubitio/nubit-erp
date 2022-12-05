<?php

namespace App\Billing\Model\Company;

use App\Billing\Model\Address\Address;

class Company
{
    public string $ruc;
    public string $razonSocial;
    public string $nombreComercial;
    public Address $address;
    public string $email;
    public string $telephone;
}
