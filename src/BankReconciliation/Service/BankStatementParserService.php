<?php

declare(strict_types=1);

namespace App\BankReconciliation\Service;

use App\BankReconciliation\Dto\BankStatementImportResult;
use App\BankReconciliation\Entity\BankAccount;
use App\BankReconciliation\Entity\BankStatementImport;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Enum\BankImportProfile;
use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads an uploaded bank statement export (.xlsx/.csv) and creates one
 * BankStatementLine per data row. Mirrors
 * App\Catalog\Service\ProductImportProcessorService's structure: skip
 * empty rows, collect per-row errors and continue, batch-flush every 50
 * rows, disconnect the worksheet when done.
 */
final readonly class BankStatementParserService
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param callable(int $current, int $total): void $onProgress called after each processed row
     */
    public function process(
        string $filePath,
        BankAccount $bankAccount,
        BankStatementImport $import,
        BankImportProfile $profile,
        callable $onProgress,
    ): BankStatementImportResult {
        $result = new BankStatementImportResult();
        $map = $profile->columnMap();

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $headerRow = $map['headerRow'];
        $dataRows = $highestRow - $headerRow;

        if ($dataRows <= 0) {
            $spreadsheet->disconnectWorksheets();

            return $result;
        }

        $processed = 0;
        $imported = 0;

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $dateRaw = $this->cellStr($sheet, $map['dateColumn'], $row);
            $glosaRaw = $this->cellStr($sheet, $map['glosaColumn'], $row);
            $referenceRaw = $map['referenceColumn'] !== null
                ? $this->cellStr($sheet, $map['referenceColumn'], $row)
                : '';

            $amountCells = $map['amountMode'] === 'single'
                ? [$this->cellStr($sheet, (string) $map['amountColumn'], $row)]
                : [
                    $this->cellStr($sheet, (string) $map['debitColumn'], $row),
                    $this->cellStr($sheet, (string) $map['creditColumn'], $row),
                ];

            if ($this->isEmptyRow([$dateRaw, $glosaRaw, ...$amountCells])) {
                $onProgress(++$processed, $dataRows);

                continue;
            }

            try {
                $line = $this->buildLine(
                    $row,
                    $dateRaw,
                    $glosaRaw,
                    $referenceRaw,
                    $amountCells,
                    $map,
                    $bankAccount,
                    $import,
                );
                $this->em->persist($line);
                $imported++;
            } catch (\Throwable $e) {
                $result->addError($row, $e->getMessage());
            }

            $onProgress(++$processed, $dataRows);

            if (($processed % self::BATCH_SIZE) === 0) {
                $this->em->flush();
            }
        }

        $result->totalRows = $dataRows;
        $result->importedRows = $imported;

        $this->em->flush();
        $spreadsheet->disconnectWorksheets();

        return $result;
    }

    /**
     * @param array{dateFormat: string, amountMode: 'single'|'debit_credit'} $map
     * @param list<string> $amountCells
     */
    private function buildLine(
        int $row,
        string $dateRaw,
        string $glosaRaw,
        string $referenceRaw,
        array $amountCells,
        array $map,
        BankAccount $bankAccount,
        BankStatementImport $import,
    ): BankStatementLine {
        if ($dateRaw === '') {
            throw new \InvalidArgumentException('Fecha vacía');
        }
        if ($glosaRaw === '') {
            throw new \InvalidArgumentException('Glosa vacía');
        }

        $date = $this->parseDate($dateRaw, $map['dateFormat']);
        $amount = $map['amountMode'] === 'single'
            ? $this->parseSignedAmount($amountCells[0])
            : $this->parseDebitCreditAmount($amountCells[0], $amountCells[1]);

        return new BankStatementLine()
            ->setBankAccount($bankAccount)
            ->setImportBatch($import)
            ->setTransactionDate($date)
            ->setAmount(Money::toString($amount))
            ->setGlosa($glosaRaw)
            ->setReference($referenceRaw !== '' ? $referenceRaw : null)
            ->setRowNumber($row);
    }

    private function parseDate(string $raw, string $format): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($format, $raw);
        if ($date === false) {
            throw new \InvalidArgumentException(sprintf(
                'Fecha "%s" no coincide con el formato esperado (%s)',
                $raw,
                $format,
            ));
        }

        return $date->setTime(0, 0);
    }

    private function parseSignedAmount(string $raw): \Brick\Math\BigDecimal
    {
        $normalized = $this->normalizeNumber($raw);
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('Monto "%s" no es un número válido', $raw));
        }

        return Money::money($normalized);
    }

    private function parseDebitCreditAmount(string $debitRaw, string $creditRaw): \Brick\Math\BigDecimal
    {
        $debit = $debitRaw !== '' ? $this->normalizeNumber($debitRaw) : '';
        $credit = $creditRaw !== '' ? $this->normalizeNumber($creditRaw) : '';

        if ($debit !== '' && is_numeric($debit)) {
            return Money::money($debit)->abs()->negated();
        }
        if ($credit !== '' && is_numeric($credit)) {
            return Money::money($credit)->abs();
        }

        throw new \InvalidArgumentException('Fila sin monto de cargo ni abono');
    }

    /** Handles thousands separators and parentheses-as-negative. */
    private function normalizeNumber(string $raw): string
    {
        $raw = trim($raw);
        $negative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        $raw = str_replace(['(', ')', ',', ' '], '', $raw);

        return $negative ? '-' . $raw : $raw;
    }

    /** @param list<string> $values */
    private function isEmptyRow(array $values): bool
    {
        return array_filter($values, static fn(string $v): bool => $v !== '') === [];
    }

    private function cellStr(Worksheet $sheet, string $col, int $row): string
    {
        return trim((string) ($sheet->getCell($col . $row)->getValue() ?? ''));
    }
}
