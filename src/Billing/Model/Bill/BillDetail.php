<?php

namespace App\Billing\Model\Bill;

class BillDetail
{
    public string $unidad;
    public int $cantidad;
    public string $codProducto;
    public string $descripcion;
    public float $mtoValorUnitario;
    public float $mtoBaseIgv;
    public float $porcentajeIgv;
    public float $igv;
    public string $tipAfeIgv;
    public float $totalImpuestos;
    public float $mtoPrecioUnitario;
    public float $mtoValorVenta;
}
