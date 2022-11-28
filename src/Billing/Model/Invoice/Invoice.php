<?php

namespace App\Billing\Model\Invoice;

use App\Billing\Model\Client\Client;
use App\Billing\Model\Company\Company;

class Invoice
{
    public string $ublVersion;
    public string $tipoOperacion;
    public string $tipoDoc;
    public string $serie;
    public string $correlativo;
    public string $fechaEmision;
    public FormaPago $formaPago;
    public Client $client;
    public Company $company;
    public string $tipoMoneda;
    public float $mtoOperGravadas;
    public float $mtoIgv;
    public float $totalImpuestos;
    public float $valorVenta;
    public float $subTotal;
    public float $mtoImpVenta;
    /** @var InvoiceDetail[] */
    public array $details;
    /** @var Legend[] */
    public array $legends;
}