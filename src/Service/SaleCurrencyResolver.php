<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\Sale;
use Doctrine\DBAL\Connection;

readonly class SaleCurrencyResolver
{
    private const string PEN = 'PEN';
    private const string USD = 'USD';

    public function __construct(
        private Connection $connection,
        private SettingsService $settings,
    ) {
    }

    /**
     * @return array{currency: string, exchange_rate: string}
     */
    public function resolveForWrite(Sale $sale): array
    {
        $saleId = $sale->getId();
        $snapshot = $saleId !== null ? $this->findInSalesTable($saleId) : null;

        $currency = $this->normalizeCurrency(
            $sale->getCurrency()
            ?? $snapshot['currency']
            ?? (string)$this->settings->get(Constants::SALES_DEFAULT_CURRENCY, self::PEN)
        );

        $exchangeRate = $this->normalizeExchangeRate(
            $currency,
            $sale->getExchangeRate() ?? $snapshot['exchange_rate']
        );

        return [
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * @return array{currency: string, exchange_rate: string}
     */
    public function resolveForSaleId(string $saleId): array
    {
        $snapshot = $this->findInSalesTable($saleId);
        $currency = $this->normalizeCurrency(
            $snapshot['currency'] ?? (string)$this->settings->get(Constants::SALES_DEFAULT_CURRENCY, self::PEN)
        );
        $exchangeRate = $this->normalizeExchangeRate($currency, $snapshot['exchange_rate']);

        return [
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * @return array{currency: ?string, exchange_rate: ?string}|null
     */
    private function findInSalesTable(string $saleId): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT s.currency, s.exchange_rate
FROM sales s
WHERE s.id = :id OR s.legacy_movimiento_id = :id
ORDER BY CASE WHEN s.id = :id THEN 0 ELSE 1 END
LIMIT 1
SQL,
            ['id' => $saleId]
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'currency' => isset($row['currency']) ? (string)$row['currency'] : null,
            'exchange_rate' => isset($row['exchange_rate']) ? (string)$row['exchange_rate'] : null,
        ];
    }

    private function normalizeCurrency(?string $value): string
    {
        $currency = strtoupper(trim((string)$value));

        if ($currency === self::USD) {
            return self::USD;
        }

        return self::PEN;
    }

    private function normalizeExchangeRate(string $currency, ?string $rawExchangeRate): string
    {
        if ($currency === self::PEN) {
            return '1.000000';
        }

        $candidate = trim((string)$rawExchangeRate);
        if ($candidate === '' || !is_numeric($candidate) || (float)$candidate <= 0) {
            $candidate = (string)$this->settings->get(Constants::SALES_DEFAULT_USD_EXCHANGE_RATE, '3.800000');
        }

        if (!is_numeric($candidate) || (float)$candidate <= 0) {
            $candidate = '1';
        }

        return number_format((float)$candidate, 6, '.', '');
    }
}
