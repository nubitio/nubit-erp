<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inventory Lot Tracking Phase 1.
 *
 * - stock_lots: tracks individual lots/batches with expiration dates
 * - stock_movements.lot_id: links movements to a specific lot
 * - producto.lot_tracking_strategy: NONE | FIFO | FEFO per product
 */
final class Version20260301210000_inventory_lot_tracking extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lot/batch tracking: stock_lots table, lot_id on stock_movements, lot_tracking_strategy on producto.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('stock_lots')) {
            $this->addSql(<<<'SQL'
CREATE TABLE stock_lots (
    id             CHAR(36)        NOT NULL,
    product_id     CHAR(36)        NOT NULL,
    warehouse_id   CHAR(36)        NOT NULL,
    lot_number     VARCHAR(100)    NOT NULL,
    expiration_date DATE           NULL,
    manufacture_date DATE          NULL,
    qty_on_hand    DECIMAL(18,6)   NOT NULL DEFAULT 0,
    cost           DECIMAL(12,4)   NULL COMMENT 'Unit cost at reception',
    received_at    DATETIME        NOT NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lot_product_warehouse_number (product_id, warehouse_id, lot_number),
    INDEX idx_lot_product_warehouse (product_id, warehouse_id),
    INDEX idx_lot_expiration (expiration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if ($this->tableExists('stock_movements') && !$this->columnExists('stock_movements', 'lot_id')) {
            $this->addSql('ALTER TABLE stock_movements ADD COLUMN lot_id CHAR(36) NULL AFTER variant_id');
            $this->addSql('ALTER TABLE stock_movements ADD INDEX idx_sm_lot_id (lot_id)');
        }

        if ($this->tableExists('producto') && !$this->columnExists('producto', 'lot_tracking_strategy')) {
            $this->addSql(
                "ALTER TABLE producto ADD COLUMN lot_tracking_strategy VARCHAR(10) NOT NULL DEFAULT 'NONE' COMMENT 'NONE | FIFO | FEFO'"
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->columnExists('stock_movements', 'lot_id')) {
            $this->addSql('ALTER TABLE stock_movements DROP INDEX idx_sm_lot_id');
            $this->addSql('ALTER TABLE stock_movements DROP COLUMN lot_id');
        }

        if ($this->columnExists('producto', 'lot_tracking_strategy')) {
            $this->addSql('ALTER TABLE producto DROP COLUMN lot_tracking_strategy');
        }

        if ($this->tableExists('stock_lots')) {
            $this->addSql('DROP TABLE stock_lots');
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
    }
}
