<?php

declare(strict_types=1);

namespace App\BankReconciliation\Dto;

/**
 * Aggregated result of one bank statement import job. Mirrors
 * App\Catalog\Dto\ProductImportResult's shape.
 */
final class BankStatementImportResult
{
    /** @param list<array{row: int, message: string}> $errors */
    public function __construct(
        public int $totalRows = 0,
        public int $importedRows = 0,
        public array $errors = [],
    ) {}

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
    }

    public function getErrorRows(): int
    {
        return count($this->errors);
    }

    /** @return array{totalRows: int, importedRows: int, errorRows: int, errors: list<array{row: int, message: string}>} */
    public function toArray(): array
    {
        return [
            'totalRows' => $this->totalRows,
            'importedRows' => $this->importedRows,
            'errorRows' => $this->getErrorRows(),
            'errors' => $this->errors,
        ];
    }
}
