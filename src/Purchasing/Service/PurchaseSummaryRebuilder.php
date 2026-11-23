<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use Doctrine\DBAL\Connection;

/**
 * Rebuilds the per-day purchase summaries that the dashboard reads, from the posted goods receipts.
 *
 * The caller owns the transaction: this only deletes and re-inserts the given range.
 */
final readonly class PurchaseSummaryRebuilder
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @param string $from first day, Y-m-d, shop (Lima) time @param string $to last day, inclusive */
    public function rebuild(string $from, string $to): void
    {
        $this->deleteRange('purchases_daily_summary', $from, $to);
        $this->deleteRange('purchase_product_daily_summary', $from, $to);

        $this->connection->executeStatement($this->purchasesDailySql(), [$from, $to]);
        $this->connection->executeStatement($this->purchaseProductDailySql(), [$from, $to]);
    }

    private function deleteRange(string $table, string $from, string $to): void
    {
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE summary_date BETWEEN :from AND :to', $table),
            ['from' => $from, 'to' => $to],
        );
    }

    private function purchasesDailySql(): string
    {
        return <<<'SQL'
            INSERT INTO purchases_daily_summary (
                summary_date, branch_id, warehouse_id,
                receipts_count, suppliers_count, items_qty,
                subtotal, tax_total, grand_total
            )
            SELECT
                DATE(CONVERT_TZ(gr.issued_at, 'UTC', 'America/Lima')) AS summary_date,
                gr.branch_id,
                gr.warehouse_id,
                COUNT(*) AS receipts_count,
                COUNT(DISTINCT gr.supplier_id) AS suppliers_count,
                ROUND(COALESCE(SUM(gri.qty), 0), 3) AS items_qty,
                ROUND(COALESCE(SUM(gr.subtotal), 0), 2) AS subtotal,
                ROUND(COALESCE(SUM(gr.tax_total), 0), 2) AS tax_total,
                ROUND(COALESCE(SUM(gr.grand_total), 0), 2) AS grand_total
            FROM goods_receipts gr
            LEFT JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
            WHERE DATE(CONVERT_TZ(gr.issued_at, 'UTC', 'America/Lima')) BETWEEN ? AND ?
              AND gr.status = 'POSTED'
              AND gr.deleted_at IS NULL
            GROUP BY DATE(CONVERT_TZ(gr.issued_at, 'UTC', 'America/Lima')), gr.branch_id, gr.warehouse_id
            SQL;
    }

    private function purchaseProductDailySql(): string
    {
        return <<<'SQL'
            INSERT INTO purchase_product_daily_summary (
                summary_date, branch_id, warehouse_id, product_id,
                receipts_count, qty_received, subtotal, tax_total, total_amount
            )
            SELECT
                DATE(CONVERT_TZ(gr.issued_at, 'UTC', 'America/Lima')) AS summary_date,
                gr.branch_id,
                gr.warehouse_id,
                gri.product_id,
                COUNT(DISTINCT gr.id) AS receipts_count,
                ROUND(COALESCE(SUM(gri.qty), 0), 3) AS qty_received,
                ROUND(COALESCE(SUM(gri.gross_amount), 0), 2) AS subtotal,
                ROUND(COALESCE(SUM(gri.tax_amount), 0), 2) AS tax_total,
                ROUND(COALESCE(SUM(gri.total_amount), 0), 2) AS total_amount
            FROM goods_receipts gr
            INNER JOIN goods_receipt_items gri ON gri.goods_receipt_id = gr.id
            WHERE DATE(CONVERT_TZ(gr.issued_at, 'UTC', 'America/Lima')) BETWEEN ? AND ?
              AND gr.status = 'POSTED'
              AND gr.deleted_at IS NULL
            GROUP BY DATE(CONVERT_TZ(gr.issued_at, 'UTC', 'America/Lima')), gr.branch_id, gr.warehouse_id, gri.product_id
            SQL;
    }
}
