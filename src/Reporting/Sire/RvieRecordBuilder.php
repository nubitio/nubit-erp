<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;

/**
 * Línea del archivo «Reemplazar propuesta» del RVIE (libro 140400): 33 campos
 * informados y el palote final. Los campos 34 a 40 los completa la
 * Administración y NO se envían — mandarlos vacíos da el error 453 («la fila
 * no cumple con la estructura»).
 *
 * Estructura según el Anexo 3 de la RS 112-2021/SUNAT; el criterio de los
 * campos 34-40 y del vencimiento vacío sale de un archivo aceptado por SUNAT
 * reportado por contaperu (https://github.com/contaperu/contaperu,
 * © 2026 Global Procesos AI S.A.C., licencia MIT), de cuyo driver `sire`
 * se porta la disposición de campos.
 */
final class RvieRecordBuilder
{
    /** Cantidad de campos informados; la fija el test de estructura. */
    public const int INFORMED_FIELDS = 33;

    public function __construct(
        private readonly string $ruc,
        private readonly string $holderName,
        private readonly string $period,
    ) {}

    public function build(RvieDocument $doc): string
    {
        $negative = $doc->isCreditNote();
        $money = static fn(BigDecimal $value): string => SireTextFormat::money($value, $negative);
        $amounts = $doc->amounts;
        $modified = $doc->modified;

        $fields = [
            $this->ruc, //                                        1  RUC del generador
            SireTextFormat::text($this->holderName), //           2  ID libre del generador
            $this->period, //                                     3  Periodo AAAAMM
            '', //                                                4  CAR SUNAT (vacío en el reemplazo)
            SireTextFormat::date($doc->issueDate), //             5  Fecha de emisión
            '', //                                                6  Vencimiento (solo tipos que lo exigen: ninguno de 01/03/07/08)
            $doc->type, //                                        7  Tipo de comprobante
            SireTextFormat::text($doc->series), //                8  Serie
            SireTextFormat::number($doc->number), //              9  Número
            '', //                                                10 Número final (rango)
            $doc->customer->docType, //                             11 Tipo de documento del cliente
            SireTextFormat::text($doc->customer->docNumber), //     12 Número de documento
            SireTextFormat::text($doc->customer->name), //          13 Razón social / nombres
            $money($amounts->export), //                              14 Valor facturado de exportación
            $money($amounts->taxable), //                             15 Base imponible gravada
            '0.00', //                                            16 Descuento de la base
            $money($amounts->igv), //                                 17 IGV / IPM
            '0.00', //                                            18 Descuento del IGV
            $money($amounts->exempt), //                              19 Exonerado
            $money($amounts->unaffected), //                          20 Inafecto
            '0.00', //                                            21 ISC
            '0.00', //                                            22 Base del IVAP
            '0.00', //                                            23 IVAP
            '0.00', //                                            24 ICBPER
            '0.00', //                                            25 Otros tributos y cargos
            $money($amounts->total), //                               26 Importe total
            $doc->currency, //                                    27 Moneda
            SireTextFormat::exchangeRate($doc->currency, $doc->exchangeRate), // 28 Tipo de cambio
            SireTextFormat::date($modified?->date), //          29 Fecha del comprobante modificado
            (string) $modified?->type, //                       30 Tipo del comprobante modificado
            SireTextFormat::text($modified?->series), //        31 Serie del comprobante modificado
            SireTextFormat::number($modified?->number), //      32 Número del comprobante modificado
            '', //                                                33 Identificador de proyecto / contrato
        ];

        return implode(SireTextFormat::SEPARATOR, $fields) . SireTextFormat::SEPARATOR;
    }
}
