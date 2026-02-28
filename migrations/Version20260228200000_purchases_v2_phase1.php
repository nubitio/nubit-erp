<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228200000_purchases_v2_phase1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purchases V2 phase 1: goods receipts and receipt items with offline metadata and legacy bridge.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE goods_receipts (
    id CHAR(36) NOT NULL,
    legacy_movimiento_id VARCHAR(255) DEFAULT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    supplier_id VARCHAR(255) DEFAULT NULL,
    buyer_user_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'POSTED',
    currency CHAR(3) NOT NULL DEFAULT 'PEN',
    exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    issued_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    local_uuid CHAR(36) DEFAULT NULL,
    idempotency_key CHAR(36) DEFAULT NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    synced_at DATETIME DEFAULT NULL,
    device_id VARCHAR(100) DEFAULT NULL,
    version_no INT NOT NULL DEFAULT 1,
    last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_goods_receipts_legacy_movimiento (legacy_movimiento_id),
    UNIQUE INDEX uq_goods_receipts_idempotency (idempotency_key),
    UNIQUE INDEX uq_goods_receipts_device_local_uuid (device_id, local_uuid),
    INDEX idx_goods_receipts_branch_issued (branch_id, issued_at),
    INDEX idx_goods_receipts_warehouse_issued (warehouse_id, issued_at),
    INDEX idx_goods_receipts_supplier_issued (supplier_id, issued_at),
    INDEX idx_goods_receipts_status_created (status, created_at),
    INDEX idx_goods_receipts_sync_status_synced (sync_status, synced_at),
    CONSTRAINT fk_goods_receipts_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_goods_receipts_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_goods_receipts_supplier FOREIGN KEY (supplier_id) REFERENCES proveedor (proveedor_id),
    CONSTRAINT fk_goods_receipts_buyer FOREIGN KEY (buyer_user_id) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_goods_receipts_status CHECK (status IN ('DRAFT', 'POSTED', 'VOIDED')),
    CONSTRAINT chk_goods_receipts_sync_status CHECK (sync_status IN ('PENDING', 'SYNCING', 'SYNCED', 'ERROR', 'CONFLICT'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE goods_receipt_items (
    id BIGINT AUTO_INCREMENT NOT NULL,
    goods_receipt_id CHAR(36) NOT NULL,
    legacy_movimiento_detalle_id INT DEFAULT NULL,
    line_no INT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) DEFAULT NULL,
    sku_snapshot VARCHAR(64) DEFAULT NULL,
    name_snapshot VARCHAR(255) NOT NULL,
    uom_snapshot VARCHAR(20) DEFAULT NULL,
    qty DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(12,4) NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    local_uuid CHAR(36) DEFAULT NULL,
    idempotency_key CHAR(36) DEFAULT NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    synced_at DATETIME DEFAULT NULL,
    device_id VARCHAR(100) DEFAULT NULL,
    version_no INT NOT NULL DEFAULT 1,
    last_modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_goods_receipt_items_line (goods_receipt_id, line_no),
    UNIQUE INDEX uq_goods_receipt_items_legacy_detail (legacy_movimiento_detalle_id),
    UNIQUE INDEX uq_goods_receipt_items_idempotency (idempotency_key),
    UNIQUE INDEX uq_goods_receipt_items_device_local_uuid (device_id, local_uuid),
    INDEX idx_goods_receipt_items_receipt (goods_receipt_id),
    INDEX idx_goods_receipt_items_product_created (product_id, created_at),
    INDEX idx_goods_receipt_items_sync_status_synced (sync_status, synced_at),
    CONSTRAINT fk_goods_receipt_items_receipt FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts (id) ON DELETE CASCADE,
    CONSTRAINT fk_goods_receipt_items_product FOREIGN KEY (product_id) REFERENCES producto (producto_id),
    CONSTRAINT chk_goods_receipt_items_sync_status CHECK (sync_status IN ('PENDING', 'SYNCING', 'SYNCED', 'ERROR', 'CONFLICT'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE goods_receipt_items');
        $this->addSql('DROP TABLE goods_receipts');
    }
}
