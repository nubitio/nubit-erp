<?php

namespace App\Billing\Model\Bill;

use App\Billing\Model\Client\Client;
use App\Billing\Model\Company\Company;

class Bill
{
    public string $ublVersion;
    public string $tipoOperacion;
    public string $tipoDoc;
    public string $serie;
    public string $correlativo;
    public \DateTimeInterface $fechaEmision;
    public PaymentTerms $formaPago;
    public Client $client;
    public Company $company;
    public string $tipoMoneda;
    public ?float $sumOtrosCargos = null;
    public float $mtoOperGravadas;
    public float $mtoIGV;
    public float $totalImpuestos;
    public float $valorVenta;
    public float $subTotal;
    public float $mtoImpVenta;
    /** @var Charge[] */
    public array $cargos = [];
    /** @var Charge[] */
    public array $descuentos = [];
    /** @var BillDetail[] */
    public array $details;
    /** @var Legend[] */
    public array $legends;
    public ?string $obs = null;

    public function getName(): string
    {
        $parts = [
            $this->company->ruc,
            $this->tipoDoc,
            $this->serie,
            $this->correlativo,
        ];

        return join('-', $parts);
    }
}
