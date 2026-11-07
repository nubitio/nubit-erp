<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;

/**
 * Línea del archivo «Reemplazar propuesta» del RCE (libro 080400): 37 campos
 * informados más los campos 38 a 41 presentes y vacíos (detracción, tipo de
 * nota, estado e inconsistencias), y el palote final. A diferencia del RVIE,
 * aquí la nota (2) del Anexo 11 pide mostrarlos vacíos y no omitirlos.
 *
 * Estructura según el Anexo 11 de la RS 040-2022/SUNAT; el criterio de los
 * cuatro campos finales sale de un RCE presentado reportado por contaperu
 * (https://github.com/contaperu/contaperu, © 2026 Global Procesos AI S.A.C.,
 * licencia MIT), de cuyo driver `sire` se porta la disposición de campos.
 */
final class RceRecordBuilder
{
    /** Campos informados más los cuatro finales vacíos. */
    public const int FIELDS = 41;

    /** Comprobantes en los que SUNAT exige fecha de vencimiento o de pago (campo 6). */
    private const array REQUIRE_DUE_DATE = ['14', '46', '50', '51', '52', '53', '54'];

    public function __construct(
        private readonly string $ruc,
        private readonly string $holderName,
        private readonly string $period,
    ) {}

    public function build(RceDocument $doc): string
    {
        $negative = $doc->isCreditNote();
        $money = static fn(BigDecimal $value): string => SireTextFormat::money($value, $negative);
        $zero = $money(BigDecimal::zero());

        $fields = [
            $this->ruc, //                                                     1  RUC del generador
            SireTextFormat::text($this->holderName), //                        2  ID libre del generador
            $this->period, //                                                  3  Periodo AAAAMM
            '', //                                                             4  CAR SUNAT (vacío en el reemplazo)
            SireTextFormat::date($doc->issueDate), //                          5  Fecha de emisión
            '', //                                                             6  Vencimiento: solo los tipos que lo exigen (ver REQUIRE_DUE_DATE)
            $doc->type, //                                                     7  Tipo de comprobante
            SireTextFormat::text($doc->series), //                             8  Serie
            '', //                                                             9  Año de emisión de la DUA
            SireTextFormat::number($doc->number), //                           10 Número
            '', //                                                             11 Número final (rango)
            $doc->supplier->docType, //                                        12 Tipo de documento del proveedor
            SireTextFormat::text($doc->supplier->docNumber), //                13 Número de documento
            SireTextFormat::text($doc->supplier->name), //                     14 Razón social / nombres
            $money($doc->taxable), //                                          15 Base imponible DG (gravadas para operaciones gravadas)
            $money($doc->igv), //                                              16 IGV DG
            $zero, //                                                          17 Base imponible DGNG
            $zero, //                                                          18 IGV DGNG
            $zero, //                                                          19 Base imponible DNG
            $zero, //                                                          20 IGV DNG
            $money($doc->notTaxed), //                                         21 Adquisiciones no gravadas
            $zero, //                                                          22 ISC
            $zero, //                                                          23 ICBPER
            $zero, //                                                          24 Otros tributos y cargos
            $money($doc->total), //                                            25 Importe total
            $doc->currency, //                                                 26 Moneda
            SireTextFormat::exchangeRate($doc->currency, $doc->exchangeRate), // 27 Tipo de cambio
            '', //                                                             28 Fecha del comprobante modificado
            '', //                                                             29 Tipo del comprobante modificado
            '', //                                                             30 Serie del comprobante modificado
            '', //                                                             31 Código de la dependencia aduanera
            '', //                                                             32 Número del comprobante modificado
            '', //                                                             33 Clasificación de bienes y servicios
            '', //                                                             34 Identificador de proyecto
            '', //                                                             35 Porcentaje de participación
            '', //                                                             36 IMB (Ley 31053)
            '', //                                                             37 CAR original (solo ajustes posteriores)
            '', //                                                             38 Marca de detracción
            '', //                                                             39 Tipo de nota
            '', //                                                             40 Estado del comprobante
            '', //                                                             41 Inconsistencias
        ];

        return implode(SireTextFormat::SEPARATOR, $fields) . SireTextFormat::SEPARATOR;
    }

    public static function requiresDueDate(string $type): bool
    {
        return in_array($type, self::REQUIRE_DUE_DATE, true);
    }
}
