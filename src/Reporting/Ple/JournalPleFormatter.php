<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

/**
 * Formato SUNAT 5.1 / 6.1: 21 campos pipe + CRLF, CUO estable entre Diario y Mayor.
 */
final class JournalPleFormatter
{
    /**
     * CUO estable entre Diario y Mayor: M + dígitos del número de asiento.
     * A2026-000046 → M2026000046
     */
    public static function cuo(string $entryNo): string
    {
        $digits = preg_replace('/\D+/', '', $entryNo) ?? '';

        return $digits === '' ? 'M1' : 'M' . $digits;
    }

    public static function lineCorrelative(int $lineNo): string
    {
        return 'M' . max(1, $lineNo);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function buildRecord(string $periodKey, array $row, string $companyRuc): string
    {
        $entryDate = JournalPleDates::toDate($row['entry_date'] ?? null);
        [$docType, $series, $number] = JournalPleIdentity::voucher($row);
        [$issuerType, $issuerDoc] = JournalPleIdentity::issuer($row, $companyRuc);
        $glosa = JournalPleDates::glosa($row);
        $currency = JournalPleDates::currency($row);

        return PleWriter::record([
            $periodKey, // 1  Periodo AAAAMM00
            self::cuo((string) ($row['entry_no'] ?? '')), // 2  CUO
            self::lineCorrelative((int) ($row['line_no'] ?? 1)), // 3  Correlativo (A/M/C)
            JournalPleDates::clip((string) ($row['account_code'] ?? ''), 24), // 4  Cuenta contable
            '', // 5  Unidad de operación
            '', // 6  Centro de costos
            $currency, // 7  Moneda de origen (Tabla 4)
            $issuerType, // 8  Tipo doc. identidad del emisor (Tabla 2)
            $issuerDoc, // 9  Número doc. identidad del emisor
            $docType, // 10 Tipo de comprobante (Tabla 10)
            $series, // 11 Serie del comprobante
            $number, // 12 Número del comprobante
            PleWriter::date($entryDate), // 13 Fecha contable
            '', // 14 Fecha de vencimiento
            PleWriter::date($entryDate), // 15 Fecha de la operación
            $glosa, // 16 Glosa
            JournalPleDates::clip((string) ($row['memo'] ?? ''), 200), // 17 Glosa referencial
            PleWriter::amount((string) ($row['debit'] ?? '0')), // 18 Debe
            PleWriter::amount((string) ($row['credit'] ?? '0')), // 19 Haber
            '', // 20 Dato estructurado (libro relacionado)
            '1', // 21 Estado (1 = dato del periodo)
        ]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function toPreviewRow(int $index, array $row, float $debit, float $credit): array
    {
        [$docType, $series, $number] = JournalPleIdentity::voucher($row);

        return [
            'index' => $index,
            'issueDate' => PleWriter::date(JournalPleDates::toDate($row['entry_date'] ?? null)),
            'cuo' => self::cuo((string) ($row['entry_no'] ?? '')),
            'correlative' => self::lineCorrelative((int) ($row['line_no'] ?? 1)),
            'accountCode' => (string) ($row['account_code'] ?? ''),
            'accountName' => (string) ($row['account_name'] ?? ''),
            'documentType' => $docType,
            'series' => $series,
            'number' => $number,
            'counterpartyDoc' => '',
            'counterpartyName' => (string) ($row['description'] ?? ''),
            'glosa' => (string) ($row['description'] ?? ''),
            'debit' => $debit,
            'credit' => $credit,
            'taxable' => 0.0,
            'igv' => 0.0,
            'total' => round($debit + $credit, 2),
            'state' => '1',
            'stateLabel' => 'Activo',
        ];
    }
}
