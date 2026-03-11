<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Context\BranchContextInterface;
use Doctrine\DBAL\Connection;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'customer_intelligence',
    description: 'Returns a purchase profile for a customer identified by RUC (11 digits), '
        . 'DNI (8 digits), or numeric customer ID. '
        . 'Includes total purchases, spending, top products, and purchase frequency.',
    method: 'customerIntelligence',
)]
final readonly class CustomerIntelligenceTool
{
    public function __construct(
        private Connection $connection,
        private BranchContextInterface $branchContextService,
    ) {
    }

    /**
     * Returns a purchase profile for a customer.
     *
     * @return array{error: string}|array{
     *     customer_id: int,
     *     name: string,
     *     document_type: string,
     *     document_number: string,
     *     total_purchases: int,
     *     total_spent: float,
     *     avg_ticket: float,
     *     first_purchase_date: string,
     *     last_purchase_date: string,
     *     top_products: list<array{product_name: string, qty: float, times_purchased: int}>,
     *     frequency: string,
     *     days_since_last_purchase: int,
     * }
     */
    public function customerIntelligence(string $identifier): array
    {
        $identifier = trim($identifier);

        // Detect identifier type and find the customer
        $customer = $this->resolveCustomer($identifier);
        if ($customer === null) {
            return ['error' => 'Cliente no encontrado'];
        }

        $customerId = (int) $customer['id'];
        $branchId = $this->branchContextService->getCurrentBranchId();

        // Build branch filter
        $branchCondition = '';
        $params = ['customer_id' => $customerId];
        if ($branchId !== null) {
            $branchCondition = 'AND s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        // Aggregated purchase stats
        $statsSql = <<<SQL
            SELECT
                COUNT(*)                        AS total_purchases,
                COALESCE(SUM(s.grand_total), 0) AS total_spent,
                MIN(s.issued_at)                AS first_purchase_date,
                MAX(s.issued_at)                AS last_purchase_date
            FROM sales s
            WHERE s.customer_id = :customer_id
              AND s.deleted_at IS NULL
              {$branchCondition}
            SQL;

        /** @var array{total_purchases: string|int, total_spent: string, first_purchase_date: string|null, last_purchase_date: string|null}|false $stats */
        $stats = $this->connection->fetchAssociative($statsSql, $params);

        if ($stats === false || (int) $stats['total_purchases'] === 0) {
            return ['error' => 'Cliente no encontrado'];
        }

        $totalPurchases = (int) $stats['total_purchases'];
        $totalSpent = (float) $stats['total_spent'];
        $avgTicket = $totalPurchases > 0 ? round($totalSpent / $totalPurchases, 2) : 0.0;

        $firstPurchaseDate = $stats['first_purchase_date'] !== null
            ? (new \DateTimeImmutable((string) $stats['first_purchase_date']))->format(\DateTimeInterface::ATOM)
            : (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $lastPurchaseDate = $stats['last_purchase_date'] !== null
            ? (new \DateTimeImmutable((string) $stats['last_purchase_date']))->format(\DateTimeInterface::ATOM)
            : (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        // Days since last purchase
        $lastPurchaseDt = $stats['last_purchase_date'] !== null
            ? new \DateTimeImmutable((string) $stats['last_purchase_date'])
            : new \DateTimeImmutable();
        $daysSince = (int) $lastPurchaseDt->diff(new \DateTimeImmutable())->days;

        // Top 5 products by total quantity purchased
        $topProductsSql = <<<SQL
            SELECT
                p.descripcion                   AS product_name,
                SUM(si.qty)                     AS qty,
                COUNT(DISTINCT si.sale_id)      AS times_purchased
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            LEFT JOIN producto p ON p.producto_id = si.product_id
            WHERE s.customer_id = :customer_id
              AND s.deleted_at IS NULL
              {$branchCondition}
            GROUP BY si.product_id, p.descripcion
            ORDER BY qty DESC
            LIMIT 5
            SQL;

        /** @var list<array{product_name: string|null, qty: string, times_purchased: string}> $topProductRows */
        $topProductRows = $this->connection->fetchAllAssociative($topProductsSql, $params);

        $topProducts = array_map(static function (array $row): array {
            return [
                'product_name' => $row['product_name'] !== null ? (string) $row['product_name'] : '(sin nombre)',
                'qty' => round((float) $row['qty'], 3),
                'times_purchased' => (int) $row['times_purchased'],
            ];
        }, $topProductRows);

        // Frequency classification
        $frequency = $this->classifyFrequency($totalPurchases, $firstPurchaseDate, $lastPurchaseDate);

        // Document type detection
        $ruc = (string) $customer['ruc'];
        $documentType = $this->detectDocumentType($ruc);

        return [
            'customer_id' => $customerId,
            'name' => (string) $customer['razon_social'],
            'document_type' => $documentType,
            'document_number' => $ruc,
            'total_purchases' => $totalPurchases,
            'total_spent' => round($totalSpent, 2),
            'avg_ticket' => $avgTicket,
            'first_purchase_date' => $firstPurchaseDate,
            'last_purchase_date' => $lastPurchaseDate,
            'top_products' => $topProducts,
            'frequency' => $frequency,
            'days_since_last_purchase' => $daysSince,
        ];
    }

    /**
     * @return array{id: string|int, ruc: string, razon_social: string}|null
     */
    private function resolveCustomer(string $identifier): ?array
    {
        // Numeric-only identifier
        if (!preg_match('/^\d+$/', $identifier)) {
            return null;
        }

        $len = strlen($identifier);

        if ($len === 11) {
            // RUC
            /** @var array{id: string|int, ruc: string, razon_social: string}|false $row */
            $row = $this->connection->fetchAssociative(
                'SELECT id, ruc, razon_social FROM cliente WHERE ruc = :ruc LIMIT 1',
                ['ruc' => $identifier],
            );
        } elseif ($len === 8) {
            // DNI
            /** @var array{id: string|int, ruc: string, razon_social: string}|false $row */
            $row = $this->connection->fetchAssociative(
                'SELECT id, ruc, razon_social FROM cliente WHERE ruc = :ruc LIMIT 1',
                ['ruc' => $identifier],
            );
        } else {
            // Numeric ID
            /** @var array{id: string|int, ruc: string, razon_social: string}|false $row */
            $row = $this->connection->fetchAssociative(
                'SELECT id, ruc, razon_social FROM cliente WHERE id = :id LIMIT 1',
                ['id' => (int) $identifier],
            );
        }

        return $row !== false ? $row : null;
    }

    private function detectDocumentType(string $ruc): string
    {
        $len = strlen($ruc);
        if ($len === 11) {
            return 'RUC';
        }
        if ($len === 8) {
            return 'DNI';
        }

        return 'OTHER';
    }

    /**
     * Classify purchase frequency.
     *
     * - frecuente:  >= 1 purchase/month on average
     * - ocasional:  >= 1 purchase/quarter on average
     * - inactivo:   less than above
     */
    private function classifyFrequency(int $totalPurchases, string $firstPurchaseDate, string $lastPurchaseDate): string
    {
        if ($totalPurchases === 0) {
            return 'inactivo';
        }

        $first = new \DateTimeImmutable($firstPurchaseDate);
        $last = new \DateTimeImmutable($lastPurchaseDate);

        $diffDays = (int) $first->diff($last)->days;

        // If only one purchase or very short history (< 1 day), treat as single-purchase
        if ($diffDays <= 0) {
            // Single or same-day: consider inactivo unless purchased very recently
            return 'ocasional';
        }

        $months = $diffDays / 30.0;
        $purchasesPerMonth = $totalPurchases / $months;

        if ($purchasesPerMonth >= 1.0) {
            return 'frecuente';
        }

        // >= 1 per quarter = 1/3 per month
        if ($purchasesPerMonth >= (1.0 / 3.0)) {
            return 'ocasional';
        }

        return 'inactivo';
    }
}
