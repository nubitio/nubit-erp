<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inventory P0 v2 — replaces the fragmented TB2/TB3/TB4 tables with a single
 * unified StockEntry model (standard ERP pattern).
 *
 * Drops: stock_adjustments, transfers, transfer_items,
 *        inventory_movements, inventory_movement_items
 *
 * Creates: stock_entries, stock_entry_items
 */
final class Version20260503000002_stock_entry extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventory P0 v2: unified stock_entries + stock_entry_items (replaces fragmented TB2-TB4 tables)';
    }

    public function up(Schema $schema): void
    {
        // ── Drop fragmented tables from P0 v1 ─────────────────────────────────
        foreach ([
            'inventory_movement_items',
            'inventory_movements',
            'transfer_items',
            'transfers',
            'stock_adjustments',
        ] as $table) {
            if ($this->tableExists($table)) {
                $this->addSql("DROP TABLE {$table}");
            }
        }

        // ── stock_entries ─────────────────────────────────────────────────────
        if (!$this->tableExists('stock_entries')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE stock_entries (
                    id                  CHAR(36)      NOT NULL  COMMENT 'UUID v7',
                    type                VARCHAR(20)   NOT NULL  COMMENT 'RECEIPT|ISSUE|TRANSFER|ADJUSTMENT',
                    warehouse_id        VARCHAR(255)  NOT NULL  COMMENT 'Source (or only) warehouse',
                    target_warehouse_id VARCHAR(255)  NULL      COMMENT 'Destination warehouse — only for TRANSFER',
                    operation_id        VARCHAR(255)  NULL      COMMENT 'FK operacion — optional for RECEIPT/ISSUE',
                    reason              VARCHAR(30)   NULL      COMMENT 'INITIAL_STOCK|COUNT_CORRECTION|LOSS|DAMAGE|THEFT|EXPIRY|OTHER — required for ADJUSTMENT',
                    note                TEXT          NULL,
                    created_by          INT           NOT NULL,
                    created_at          DATETIME      NOT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_se_type          (type),
                    INDEX idx_se_warehouse     (warehouse_id),
                    INDEX idx_se_tgt_warehouse (target_warehouse_id),
                    INDEX idx_se_created       (created_at),
                    CONSTRAINT fk_se_warehouse     FOREIGN KEY (warehouse_id)        REFERENCES almacen   (almacen_id),
                    CONSTRAINT fk_se_tgt_warehouse FOREIGN KEY (target_warehouse_id) REFERENCES almacen   (almacen_id),
                    CONSTRAINT fk_se_operation     FOREIGN KEY (operation_id)        REFERENCES operacion (operacion_id),
                    CONSTRAINT fk_se_user          FOREIGN KEY (created_by)          REFERENCES usuario   (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        // ── stock_entry_items ─────────────────────────────────────────────────
        if (!$this->tableExists('stock_entry_items')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE stock_entry_items (
                    id          BIGINT        NOT NULL AUTO_INCREMENT,
                    entry_id    CHAR(36)      NOT NULL,
                    product_id  VARCHAR(255)  NOT NULL,
                    qty         DECIMAL(18,6) NOT NULL  COMMENT 'User-entered qty: absolute for ADJUSTMENT, delta for others',
                    qty_before  DECIMAL(18,6) NULL      COMMENT 'Snapshot before change — set by processor for ADJUSTMENT',
                    qty_delta   DECIMAL(18,6) NULL      COMMENT 'Actual signed delta applied to ledger — set by processor',
                    unit_id     INT           NULL,
                    note        TEXT          NULL,
                    PRIMARY KEY (id),
                    INDEX idx_sei_entry   (entry_id),
                    INDEX idx_sei_product (product_id),
                    CONSTRAINT fk_sei_entry   FOREIGN KEY (entry_id)   REFERENCES stock_entries (id) ON DELETE CASCADE,
                    CONSTRAINT fk_sei_product FOREIGN KEY (product_id) REFERENCES producto       (producto_id),
                    CONSTRAINT fk_sei_unit    FOREIGN KEY (unit_id)    REFERENCES unidad         (unidad_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS stock_entry_items');
        $this->addSql('DROP TABLE IF EXISTS stock_entries');
    }

    private function tableExists(string $table): bool
    {
        $db    = $this->connection->getDatabase();
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, $table],
        );

        return (int) $count > 0;
    }
}
