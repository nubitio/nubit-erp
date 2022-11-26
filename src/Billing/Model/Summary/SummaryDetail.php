<?php

namespace App\Billing\Model\Summary;

use App\Billing\Model\Sale\Document;

class SummaryDetail
{
    public string $tipoDoc;
    public string $serieNro;
    public string $clienteTipo;
    public string $clienteNro;
    public Document $docReferencia;
    public SummaryPerception $percepcion;
    public string $estado;
    public float $total;
    public float $mtoOperGravadas;
    public float $mtoOperInafectas;
    public float $mtoOperExoneradas;
    public float $mtoOperExportacion;
    public float $mtoOperGratuitas;
    public float $mtoOtrosCargos;
    public float $mtoIgv;
    public float $mtoIvap;
    public float $mtoIcbper;
    public float $mtoIsc;
    public float $mtoOtrosTributos;
}