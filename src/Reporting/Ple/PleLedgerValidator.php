<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

/**
 * Reglas básicas de consistencia para libros PLE antes de envío a SUNAT.
 */
final class PleLedgerValidator
{
    private const SALE_DOC_TYPES = ['01', '03', '07', '08'];

    /**
     * @param array<string, mixed> $book
     *
     * @return list<array{severity: string, row: ?int, code: string, message: string}>
     */
    public function validateSalesBook(array $book, string $period): array
    {
        if (($book['status'] ?? '') === 'unavailable') {
            return [];
        }

        $issues = [];
        $rows = $book['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        if ($rows === []) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        foreach ($rows as $row) {
            $index = isset($row['index']) ? (int) $row['index'] : null;
            $this->assertDateInPeriod($issues, $index, (string) ($row['issueDate'] ?? ''), $period);
            $this->assertDocumentType($issues, $index, (string) ($row['documentType'] ?? ''), self::SALE_DOC_TYPES);
            $this->assertRequiredField(
                $issues,
                $index,
                'series',
                (string) ($row['series'] ?? ''),
                'La serie es obligatoria.',
            );
            $this->assertRequiredField(
                $issues,
                $index,
                'number',
                (string) ($row['number'] ?? ''),
                'El número es obligatorio.',
            );

            $documentType = (string) ($row['documentType'] ?? '');
            if ($documentType === '01') {
                $this->assertRuc(
                    $issues,
                    $index,
                    (string) ($row['counterpartyDoc'] ?? ''),
                    'La factura requiere RUC de 11 dígitos.',
                );
            }

            if (($row['state'] ?? '1') === '1') {
                $this->assertAmounts($issues, $index, $row);
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $book
     *
     * @return list<array{severity: string, row: ?int, code: string, message: string}>
     */
    public function validatePurchasesBook(array $book, string $period): array
    {
        if (($book['status'] ?? '') === 'unavailable') {
            return [];
        }

        $issues = [];
        $rows = $book['rows'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        if ($rows === []) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        foreach ($rows as $row) {
            $index = isset($row['index']) ? (int) $row['index'] : null;
            $this->assertDateInPeriod($issues, $index, (string) ($row['issueDate'] ?? ''), $period);
            $this->assertPurchaseDocumentType($issues, $index, (string) ($row['documentType'] ?? ''));
            $this->assertRequiredField(
                $issues,
                $index,
                'series',
                (string) ($row['series'] ?? ''),
                'La serie del comprobante de compra es obligatoria.',
            );
            $this->assertRequiredField(
                $issues,
                $index,
                'number',
                (string) ($row['number'] ?? ''),
                'El número del comprobante de compra es obligatorio.',
            );
            $this->assertAmounts($issues, $index, $row);
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $book
     *
     * @return list<array{severity: string, row: ?int, code: string, message: string}>
     */
    public function validateJournalBook(array $book, string $period): array
    {
        if (($book['status'] ?? '') === 'unavailable') {
            return [];
        }

        $rows = $book['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $issues = [];
        $byCuo = [];

        /** @var list<array<string, mixed>> $rows */
        foreach ($rows as $row) {
            $rawIndex = $row['index'] ?? null;
            $index = $rawIndex === null ? null : (int) $rawIndex;
            $this->assertDateInPeriod($issues, $index, (string) ($row['issueDate'] ?? ''), $period);
            $this->assertRequiredField(
                $issues,
                $index,
                'account',
                (string) ($row['accountCode'] ?? ''),
                'La cuenta contable es obligatoria.',
            );

            $debit = (float) ($row['debit'] ?? 0);
            $credit = (float) ($row['credit'] ?? 0);
            if (abs($debit) < 0.005 && abs($credit) < 0.005) {
                $issues[] = $this->issue($index, 'ZERO_LINE', 'La línea no tiene debe ni haber.');
            }

            $cuo = (string) ($row['cuo'] ?? '');
            $correlative = (string) ($row['correlative'] ?? '');
            if ($correlative !== '' && !preg_match('/^[AMC]/', $correlative)) {
                $issues[] = $this->issue(
                    $index,
                    'INVALID_CORRELATIVE',
                    'El correlativo del asiento debe iniciar con A, M o C.',
                );
            }

            if ($cuo !== '') {
                $byCuo[$cuo] ??= ['debit' => 0.0, 'credit' => 0.0, 'index' => $index];
                $byCuo[$cuo]['debit'] += $debit;
                $byCuo[$cuo]['credit'] += $credit;
            }
        }

        foreach ($byCuo as $cuo => $sum) {
            if (abs($sum['debit'] - $sum['credit']) <= 0.02) {
                continue;
            }

            $issues[] = $this->issue(
                $sum['index'],
                'UNBALANCED_ENTRY',
                sprintf(
                    'El asiento %s no cuadra (debe %s, haber %s).',
                    $cuo,
                    PleWriter::amount($sum['debit']),
                    PleWriter::amount($sum['credit']),
                ),
            );
        }

        return $issues;
    }

    /**
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     */
    private function assertDateInPeriod(array &$issues, ?int $row, string $issueDate, string $period): void
    {
        if ($issueDate === '') {
            $issues[] = $this->issue($row, 'MISSING_DATE', 'La fecha de emisión es obligatoria.');

            return;
        }

        $parsed = \DateTimeImmutable::createFromFormat('d/m/Y', $issueDate);
        if ($parsed === false || $parsed->format('Ym') !== $period) {
            $issues[] = $this->issue(
                $row,
                'DATE_OUT_OF_PERIOD',
                'La fecha de emisión debe pertenecer al periodo seleccionado.',
            );
        }
    }

    /**
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     * @param list<string> $allowed
     */
    private function assertDocumentType(array &$issues, ?int $row, string $documentType, array $allowed): void
    {
        if ($documentType === '' || !in_array($documentType, $allowed, true)) {
            $issues[] = $this->issue(
                $row,
                'INVALID_DOC_TYPE',
                'El tipo de comprobante no es válido para el registro de ventas.',
            );
        }
    }

    /**
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     */
    private function assertPurchaseDocumentType(array &$issues, ?int $row, string $documentType): void
    {
        if ($documentType === '' || $documentType === '00') {
            $issues[] = $this->issue(
                $row,
                'MISSING_SUPPLIER_DOC_TYPE',
                'Complete el tipo de comprobante del proveedor en la recepción.',
            );
        }
    }

    /**
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     */
    private function assertRequiredField(array &$issues, ?int $row, string $field, string $value, string $message): void
    {
        if (trim($value) === '') {
            $issues[] = $this->issue($row, 'MISSING_' . strtoupper($field), $message);
        }
    }

    /**
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     */
    private function assertRuc(array &$issues, ?int $row, string $doc, string $message): void
    {
        if (!preg_match('/^\d{11}$/', trim($doc))) {
            $issues[] = $this->issue($row, 'INVALID_RUC', $message);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{severity: string, row: ?int, code: string, message: string}> $issues
     */
    private function assertAmounts(array &$issues, ?int $rowIndex, array $row): void
    {
        $taxable = (float) ($row['taxable'] ?? 0);
        $igv = (float) ($row['igv'] ?? 0);
        $total = (float) ($row['total'] ?? 0);

        if (abs($total - ($taxable + $igv)) > 0.02) {
            $issues[] = $this->issue($rowIndex, 'AMOUNT_MISMATCH', 'El total no coincide con base imponible + IGV.');
        }
    }

    /**
     * @return array{severity: string, row: ?int, code: string, message: string}
     */
    private function issue(?int $row, string $code, string $message): array
    {
        return [
            'severity' => 'error',
            'row' => $row,
            'code' => $code,
            'message' => $message,
        ];
    }
}
