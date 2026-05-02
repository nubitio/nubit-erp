<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inventory P0 — creates all tables required for:
 *   TB2: stock_adjustments
 *   TB3: transfers + transfer_items
 *   TB4: inventory_movements + inventory_movement_items
 */
final class Version20260503000001_inventory_p0 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventory P0: stock_adjustments, transfers, transfer_items, inventory_movements, inventory_movement_items';
    }

    public function up(Schema $schema): void
    {
        // ── TB2: Stock Adjustments ─────────────────────────────────────────────
        if (!$this->tableExists('stock_adjustments')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE stock_adjustments (
                    id           CHAR(36)        NOT NULL COMMENT 'UUID v7',
                    warehouse_id VARCHAR(255)    NOT NULL,
                    product_id   VARCHAR(255)    NOT NULL,
                    qty_before   DECIMAL(18,6)   NOT NULL COMMENT 'Snapshot of qty_on_hand before adjustment',
                    qty_adjusted DECIMAL(18,6)   NOT NULL COMMENT 'New real quantity set by user',
                    qty_delta    DECIMAL(18,6)   NOT NULL COMMENT 'qty_adjusted - qty_before (can be negative)',
                    reason       VARCHAR(30)     NOT NULL COMMENT 'INITIAL_STOCK|COUNT_CORRECTION|LOSS|DAMAGE|THEFT|EXPIRY|OTHER',
                    note         TEXT            NULL,
                    created_by   INT             NOT NULL,
                    created_at   DATETIME        NOT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_adj_warehouse_product (warehouse_id, product_id),
                    INDEX idx_adj_created_at        (created_at),
                    CONSTRAINT fk_adj_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen   (almacen_id),
                    CONSTRAINT fk_adj_product   FOREIGN KEY (product_id)   REFERENCES producto  (producto_id),
                    CONSTRAINT fk_adj_user      FOREIGN KEY (created_by)   REFERENCES usuario   (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        // ── TB3: Transfers ─────────────────────────────────────────────────────
        if (!$this->tableExists('transfers')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE transfers (
                    id                  CHAR(36)     NOT NULL COMMENT 'UUID v7',
                    source_warehouse_id VARCHAR(255) NOT NULL,
                    target_warehouse_id VARCHAR(255) NOT NULL,
                    note                TEXT         NULL,
                    created_by          INT          NOT NULL,
                    created_at          DATETIME     NOT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_transfer_source  (source_warehouse_id),
                    INDEX idx_transfer_target  (target_warehouse_id),
                    INDEX idx_transfer_created (created_at),
                    CONSTRAINT fk_transfer_source FOREIGN KEY (source_warehouse_id) REFERENCES almacen (almacen_id),
                    CONSTRAINT fk_transfer_target FOREIGN KEY (target_warehouse_id) REFERENCES almacen (almacen_id),
                    CONSTRAINT fk_transfer_user   FOREIGN KEY (created_by)          REFERENCES usuario (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$this->tableExists('transfer_items')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE transfer_items (
                    id          BIGINT       NOT NULL AUTO_INCREMENT,
                    transfer_id CHAR(36)     NOT NULL,
                    product_id  VARCHAR(255) NOT NULL,
                    qty         DECIMAL(18,6) NOT NULL,
                    unit_id     INT          NULL,
                    PRIMARY KEY (id),
                    INDEX idx_transfer_items_transfer (transfer_id),
                    CONSTRAINT fk_ti_transfer FOREIGN KEY (transfer_id) REFERENCES transfers (id) ON DELETE CASCADE,
                    CONSTRAINT fk_ti_product  FOREIGN KEY (product_id)  REFERENCES producto  (producto_id),
                    CONSTRAINT fk_ti_unit     FOREIGN KEY (unit_id)     REFERENCES unidad    (unidad_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        // ── TB4: Inventory Movements (manual inbound / outbound) ──────────────
        if (!$this->tableExists('inventory_movements')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE inventory_movements (
                    id           CHAR(36)     NOT NULL COMMENT 'UUID v7',
                    type         TINYINT(1)   NOT NULL COMMENT '1=INBOUND, 2=OUTBOUND',
                    operation_id VARCHAR(255) NOT NULL COMMENT 'FK operacion.operacion_id',
                    warehouse_id VARCHAR(255) NOT NULL,
                    note         TEXT         NULL,
                    created_by   INT          NOT NULL,
                    created_at   DATETIME     NOT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_inv_mov_warehouse (warehouse_id),
                    INDEX idx_inv_mov_operation (operation_id),
                    INDEX idx_inv_mov_created   (created_at),
                    CONSTRAINT fk_inv_mov_operation FOREIGN KEY (operation_id) REFERENCES operacion (operacion_id),
                    CONSTRAINT fk_inv_mov_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen   (almacen_id),
                    CONSTRAINT fk_inv_mov_user      FOREIGN KEY (created_by)   REFERENCES usuario   (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$this->tableExists('inventory_movement_items')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE inventory_movement_items (
                    id          BIGINT        NOT NULL AUTO_INCREMENT,
                    movement_id CHAR(36)      NOT NULL,
                    product_id  VARCHAR(255)  NOT NULL,
                    qty         DECIMAL(18,6) NOT NULL,
                    unit_id     INT           NULL,
                    note        TEXT          NULL,
                    PRIMARY KEY (id),
                    INDEX idx_inv_mov_items_movement (movement_id),
                    CONSTRAINT fk_imi_movement FOREIGN KEY (movement_id) REFERENCES inventory_movements (id) ON DELETE CASCADE,
                    CONSTRAINT fk_imi_product  FOREIGN KEY (product_id)  REFERENCES producto            (producto_id),
                    CONSTRAINT fk_imi_unit     FOREIGN KEY (unit_id)     REFERENCES unidad              (unidad_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS inventory_movement_items');
        $this->addSql('DROP TABLE IF EXISTS inventory_movements');
        $this->addSql('DROP TABLE IF EXISTS transfer_items');
        $this->addSql('DROP TABLE IF EXISTS transfers');
        $this->addSql('DROP TABLE IF EXISTS stock_adjustments');
    }

    // ─── Helpers (same pattern as existing migrations) ────────────────────────

    private function tableExists(string $table): bool
    {
        $db = $this->connection->getDatabase();
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$db, $table]
        );

        return (int) $count > 0;
    }
}
