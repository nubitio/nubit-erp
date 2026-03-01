<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Core Inventory v2 — Phase 1b: Additive schema changes.
 *
 * All changes are purely additive (ADD COLUMN / CREATE TABLE / ADD INDEX).
 * Zero-downtime: no DROP, no ALTER with semantics change, no data loss.
 *
 * Changes:
 * 1. producto: + product_kind, + inventory_behavior, + sku, + barcode
 * 2. stock_items: + last_movement_id, + version
 * 3. CREATE product_bom_items
 * 4. CREATE inventory_reconciliation_runs
 */
final class Version20260301220000_inventory_core_v2_phase1b extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Core Inventory v2 Phase 1b: product_kind, inventory_behavior, sku, barcode, BOM, reconciliation, stock_items versioning';
    }

    public function up(Schema $schema): void
    {
        // ── 1. producto ──────────────────────────────────────────────────────────
        if (!$this->columnExists('producto', 'product_kind')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE producto
                    ADD COLUMN product_kind VARCHAR(10) NOT NULL DEFAULT 'GOOD'
                        COMMENT 'GOOD | SERVICE | DIGITAL'
                        AFTER product_type,
                    ADD COLUMN inventory_behavior VARCHAR(12) NOT NULL DEFAULT 'TRACKED'
                        COMMENT 'TRACKED | NOT_TRACKED | COMPOSITE'
                        AFTER product_kind,
                    ADD COLUMN sku VARCHAR(64) NULL DEFAULT NULL
                        COMMENT 'Unique SKU per tenant'
                        AFTER inventory_behavior,
                    ADD COLUMN barcode VARCHAR(64) NULL DEFAULT NULL
                        COMMENT 'Barcode (EAN-13, etc.)'
                        AFTER sku
                SQL
            );
        }

        // Unique index on sku (nullable, only unique non-null values)
        if (!$this->indexExists('producto', 'UNIQ_producto_sku')) {
            $this->addSql(
                'CREATE UNIQUE INDEX UNIQ_producto_sku ON producto (sku)'
            );
        }

        // Regular index on barcode (not unique — same barcode on different pack sizes allowed)
        if (!$this->indexExists('producto', 'IDX_producto_barcode')) {
            $this->addSql(
                'CREATE INDEX IDX_producto_barcode ON producto (barcode)'
            );
        }

        // Index for kind + behavior queries (e.g. "all tracked GOOD products")
        if (!$this->indexExists('producto', 'IDX_producto_kind_behavior')) {
            $this->addSql(
                'CREATE INDEX IDX_producto_kind_behavior ON producto (product_kind, inventory_behavior)'
            );
        }

        // ── 2. stock_items ────────────────────────────────────────────────────────
        if (!$this->columnExists('stock_items', 'last_movement_id')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE stock_items
                    ADD COLUMN last_movement_id CHAR(36) NULL DEFAULT NULL
                        COMMENT 'FK → stock_movements.id (last applied movement)'
                        AFTER qty_reserved,
                    ADD COLUMN version BIGINT UNSIGNED NOT NULL DEFAULT 0
                        COMMENT 'Optimistic concurrency version counter'
                        AFTER last_movement_id
                SQL
            );
        }

        // ── 3. product_bom_items ──────────────────────────────────────────────────
        if (!$this->tableExists('product_bom_items')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE product_bom_items (
                    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    parent_product_id  VARCHAR(255) NOT NULL
                        COMMENT 'Product with inventory_behavior=COMPOSITE',
                    component_product_id VARCHAR(255) NOT NULL
                        COMMENT 'Product with inventory_behavior=TRACKED',
                    component_qty_base DECIMAL(18,6) NOT NULL
                        COMMENT 'Quantity in base unit per 1 unit of parent',
                    wastage_factor DECIMAL(7,4) NOT NULL DEFAULT 1.0000
                        COMMENT 'Multiplier for wastage (1.0 = no wastage, 1.05 = 5% extra)',
                    is_optional   TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'If 1, component is substitutable and can be omitted',
                    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

                    UNIQUE KEY uq_bom_parent_component (parent_product_id, component_product_id),
                    KEY idx_bom_parent (parent_product_id),
                    KEY idx_bom_component (component_product_id),

                    CONSTRAINT fk_bom_parent FOREIGN KEY (parent_product_id)
                        REFERENCES producto (producto_id) ON DELETE CASCADE,
                    CONSTRAINT fk_bom_component FOREIGN KEY (component_product_id)
                        REFERENCES producto (producto_id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL
            );
        }

        // ── 4. inventory_reconciliation_runs ─────────────────────────────────────
        if (!$this->tableExists('inventory_reconciliation_runs')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE inventory_reconciliation_runs (
                    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    run_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    warehouse_id    VARCHAR(255) NOT NULL,
                    product_id      VARCHAR(255) NOT NULL,
                    ledger_sum      DECIMAL(18,6) NOT NULL
                        COMMENT 'SUM(stock_movements.qty_delta_base)',
                    stock_items_qty DECIMAL(18,6) NOT NULL
                        COMMENT 'stock_items.qty_on_hand at time of check',
                    diff            DECIMAL(18,6) NOT NULL
                        COMMENT 'ledger_sum - stock_items_qty',
                    status          VARCHAR(10) NOT NULL DEFAULT 'OK'
                        COMMENT 'OK | DRIFT | ERROR',
                    note            TEXT NULL,

                    KEY idx_reconciliation_run_at (run_at),
                    KEY idx_reconciliation_warehouse_product (warehouse_id, product_id),
                    KEY idx_reconciliation_status (status),

                    CONSTRAINT fk_reconciliation_warehouse FOREIGN KEY (warehouse_id)
                        REFERENCES almacen (almacen_id) ON DELETE CASCADE,
                    CONSTRAINT fk_reconciliation_product FOREIGN KEY (product_id)
                        REFERENCES producto (producto_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Drop new tables first (FK order)
        if ($this->tableExists('inventory_reconciliation_runs')) {
            $this->addSql('DROP TABLE inventory_reconciliation_runs');
        }

        if ($this->tableExists('product_bom_items')) {
            $this->addSql('DROP TABLE product_bom_items');
        }

        // Revert stock_items columns
        if ($this->columnExists('stock_items', 'version')) {
            $this->addSql('ALTER TABLE stock_items DROP COLUMN version, DROP COLUMN last_movement_id');
        }

        // Revert producto columns and indexes
        if ($this->indexExists('producto', 'IDX_producto_kind_behavior')) {
            $this->addSql('DROP INDEX IDX_producto_kind_behavior ON producto');
        }
        if ($this->indexExists('producto', 'IDX_producto_barcode')) {
            $this->addSql('DROP INDEX IDX_producto_barcode ON producto');
        }
        if ($this->indexExists('producto', 'UNIQ_producto_sku')) {
            $this->addSql('DROP INDEX UNIQ_producto_sku ON producto');
        }
        if ($this->columnExists('producto', 'product_kind')) {
            $this->addSql(
                'ALTER TABLE producto DROP COLUMN product_kind, DROP COLUMN inventory_behavior, DROP COLUMN sku, DROP COLUMN barcode'
            );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function tableExists(string $table): bool
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return (int) $result->fetchOne() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return (int) $result->fetchOne() > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );

        return (int) $result->fetchOne() > 0;
    }
}
