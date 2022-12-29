<?php

namespace App\Billing\Model\Bill;

class PagoContado extends PaymentTerms
{
    public function __construct()
    {
        $this->tipo = 'Contado';
        $this->moneda = null;
        $this->monto = null;
    }
}
