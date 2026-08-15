<?php

declare(strict_types=1);

namespace App\BankReconciliation\Enum;

/**
 * Column layout preset per Peruvian bank export. Plain PHP enum (not
 * Doctrine-backed) — BankStatementImport::$bankProfile stays a string
 * column so adding a new bank later doesn't require a migration. Mirrors
 * the Spanish-label → enum-value maps used in ProductImportProcessorService,
 * just for column positions instead of field values.
 */
enum BankImportProfile: string
{
    case BCP = 'BCP';
    case BBVA = 'BBVA';
    case INTERBANK = 'INTERBANK';
    case GENERIC = 'GENERIC';

    /**
     * @return array{
     *     headerRow: int,
     *     dateColumn: string,
     *     dateFormat: string,
     *     glosaColumn: string,
     *     referenceColumn: string|null,
     *     amountMode: 'single'|'debit_credit',
     *     amountColumn: string|null,
     *     debitColumn: string|null,
     *     creditColumn: string|null,
     * }
     */
    public function columnMap(): array
    {
        return match ($this) {
            self::BCP => [
                'headerRow' => 1,
                'dateColumn' => 'A',
                'dateFormat' => 'd/m/Y',
                'glosaColumn' => 'B',
                'referenceColumn' => 'C',
                'amountMode' => 'debit_credit',
                'amountColumn' => null,
                'debitColumn' => 'D',
                'creditColumn' => 'E',
            ],
            self::BBVA => [
                'headerRow' => 1,
                'dateColumn' => 'A',
                'dateFormat' => 'd/m/Y',
                'glosaColumn' => 'B',
                'referenceColumn' => null,
                'amountMode' => 'single',
                'amountColumn' => 'C',
                'debitColumn' => null,
                'creditColumn' => null,
            ],
            self::INTERBANK => [
                'headerRow' => 1,
                'dateColumn' => 'A',
                'dateFormat' => 'Y-m-d',
                'glosaColumn' => 'C',
                'referenceColumn' => 'B',
                'amountMode' => 'debit_credit',
                'amountColumn' => null,
                'debitColumn' => 'D',
                'creditColumn' => 'E',
            ],
            self::GENERIC => [
                'headerRow' => 1,
                'dateColumn' => 'A',
                'dateFormat' => 'd/m/Y',
                'glosaColumn' => 'B',
                'referenceColumn' => null,
                'amountMode' => 'single',
                'amountColumn' => 'C',
                'debitColumn' => null,
                'creditColumn' => null,
            ],
        };
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return array_map(static fn(self $case): string => $case->value, self::cases());
    }
}
