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
    public int $total;
    public int $mtoOperGravadas;
    public int $mtoOperInafectas;
    public int $mtoOperExoneradas;
    public int $mtoOperExportacion;
    public int $mtoOperGratuitas;
    public int $mtoOtrosCargos;
    public int $mtoIgv;
    public int $mtoIvap;
    public int $mtoIcbper;
    public int $mtoIsc;
    public int $mtoOtrosTributos;
}