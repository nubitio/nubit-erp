<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

use App\Accounting\Message\EconomicEvent;

/**
 * Tipo, serie y número del comprobante, y documento del emisor, para PLE 5.1/6.1.
 */
final class JournalPleIdentity
{
    /**
     * @param array<string, mixed> $row
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function voucher(array $row): array
    {
        return match ((string) ($row['source'] ?? '')) {
            EconomicEvent::SOURCE_SALE => [
                (string) ($row['sale_doc_type'] ?? ''),
                (string) ($row['sale_series'] ?? ''),
                (string) ($row['sale_number'] ?? ''),
            ],
            EconomicEvent::SOURCE_PURCHASE => [
                self::purchaseDocType((string) ($row['supplier_doc_type'] ?? '')),
                (string) ($row['supplier_doc_series'] ?? ''),
                (string) ($row['supplier_doc_number'] ?? ''),
            ],
            default => ['', '', ''],
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{0: string, 1: string}
     */
    public static function issuer(array $row, string $companyRuc): array
    {
        return match ((string) ($row['source'] ?? '')) {
            EconomicEvent::SOURCE_SALE => self::companyIssuer($companyRuc),
            EconomicEvent::SOURCE_PURCHASE => self::identityDocument((string) ($row['supplier_ruc'] ?? '')),
            default => ['', ''],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function companyIssuer(string $companyRuc): array
    {
        if (preg_match('/^\d{11}$/', $companyRuc) !== 1) {
            return ['', ''];
        }

        return ['6', $companyRuc];
    }

    private static function purchaseDocType(string $type): string
    {
        return $type === '00' ? '' : $type;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function identityDocument(string $doc): array
    {
        $doc = trim($doc);
        if ($doc === '') {
            return ['', ''];
        }

        return match (strlen($doc)) {
            11 => ['6', $doc],
            8 => ['1', $doc],
            default => ['0', $doc],
        };
    }
}
