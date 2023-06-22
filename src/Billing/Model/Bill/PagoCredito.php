<?php

namespace App\Billing\Model\Bill;

class PagoCredito extends PaymentTerms
{
    public function __construct(float $monto = null, string $moneda = null)
    {
        $this->tipo = 'Credito';
        $this->monto = $monto;
        $this->moneda = $moneda;
    }
}
