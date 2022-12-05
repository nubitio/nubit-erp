<?php

namespace App\Billing\Model\Bill;

class Charge
{
    public string $codTipo;
    public float $factor;
    public float $monto;
    public float $montoBase;
}