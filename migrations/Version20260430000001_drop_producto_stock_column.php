<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the legacy denormalised `stock` column from the `producto` table.
 *
 * Background
 * ----------
 * The `producto.stock` column was a running counter decremented on every sale.
 * It became stale as soon as the Inventory V2 `stock_items` read-model was
 * introduced (ADR-009 / ADR-018).  All live stock tracking now lives in
 * `stock_items` (keyed by warehouse_id + product_id).
 *
 * Data migration
 * --------------
 * Any product that still carries a positive `stock` value **and** has no
 * corresponding `stock_items` row gets its stock inserted into the first
 * available warehouse before the column is dropped.
 * Negative values (decrement artefacts) and zero values are ignored.
 *
 * Android POS
 * -----------
 * The Android POS `ProductDto.stock` field is `Double?` (nullable).  It will
 * simply receive `null` / a missing field after this migration, which is the
 * same value it already caches locally (ProductCacheMapper sets stock = null).
 * No Android release is required.
 */
final class Version20260430000001_drop_producto_stock_column extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy producto.stock denormalised column; migrate remaining positive values to stock_items';
    }

    public function up(Schema $schema): void
    {
        // Guard: skip if column is already gone (idempotent re-run)
        $this->skipIf(
            !$this->columnExists('producto', 'stock'),
            'Column producto.stock already dropped'
        );

        // --- Data migration: positive orphan stock → stock_items --------
        // For each product with stock > 0 that has no stock_items entry in
        // any warehouse, insert into the first available warehouse.
        $this->addSql(<<<'SQL'
            INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, version, updated_at)
            SELECT
                (SELECT almacen_id FROM almacen ORDER BY almacen_id LIMIT 1) AS warehouse_id,
                p.producto_id                                                  AS product_id,
                p.stock                                                        AS qty_on_hand,
                0                                                              AS qty_reserved,
                1                                                              AS version,
                NOW()                                                          AS updated_at
            FROM producto p
            WHERE p.stock > 0
              AND NOT EXISTS (
                  SELECT 1 FROM stock_items si WHERE si.product_id = p.producto_id
              )
              AND (SELECT COUNT(*) FROM almacen) > 0
        SQL);

        // --- Drop the column -------------------------------------------
        $this->addSql('ALTER TABLE producto DROP COLUMN stock');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE producto ADD COLUMN stock DECIMAL(12,3) DEFAULT NULL COMMENT "(DC2Type:decimal)"'
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = :table
                AND COLUMN_NAME  = :column',
            ['table' => $table, 'column' => $column]
        );

        return (int) $result->fetchOne() > 0;
    }
}
