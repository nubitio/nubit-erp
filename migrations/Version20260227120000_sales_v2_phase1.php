<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227120000_sales_v2_phase1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Sales V2 tables (sales, sale_items, sale_payments, sale_discounts, sale_taxes) for phase 1 migration with mixed payments support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE sales (
    id CHAR(36) NOT NULL,
    legacy_movimiento_id VARCHAR(255) DEFAULT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    cash_register_id INT NOT NULL,
    cash_session_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    seller_user_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'COMPLETED',
    currency CHAR(3) NOT NULL DEFAULT 'PEN',
    exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    change_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    issued_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    local_uuid CHAR(36) DEFAULT NULL,
    idempotency_key CHAR(36) DEFAULT NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    synced_at DATETIME DEFAULT NULL,
    device_id VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_sales_legacy_movimiento (legacy_movimiento_id),
    UNIQUE INDEX uq_sales_local_uuid (local_uuid),
    UNIQUE INDEX uq_sales_idempotency_key (idempotency_key),
    INDEX idx_sales_branch_issued (branch_id, issued_at),
    INDEX idx_sales_warehouse_issued (warehouse_id, issued_at),
    INDEX idx_sales_cash_session_issued (cash_session_id, issued_at),
    INDEX idx_sales_customer_issued (customer_id, issued_at),
    INDEX idx_sales_status_created (status, created_at),
    CONSTRAINT fk_sales_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_sales_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_sales_cash_register FOREIGN KEY (cash_register_id) REFERENCES cash_register (id),
    CONSTRAINT fk_sales_cash_session FOREIGN KEY (cash_session_id) REFERENCES cash_shift (id),
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES cliente (id),
    CONSTRAINT fk_sales_seller FOREIGN KEY (seller_user_id) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_sales_status CHECK (status IN ('DRAFT', 'COMPLETED', 'VOIDED', 'PARTIALLY_RETURNED', 'RETURNED')),
    CONSTRAINT chk_sales_sync_status CHECK (sync_status IN ('PENDING', 'SYNCING', 'SYNCED', 'ERROR', 'CONFLICT'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sale_items (
    id BIGINT AUTO_INCREMENT NOT NULL,
    sale_id CHAR(36) NOT NULL,
    legacy_movimiento_detalle_id INT DEFAULT NULL,
    line_no INT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) DEFAULT NULL,
    sku_snapshot VARCHAR(64) DEFAULT NULL,
    name_snapshot VARCHAR(255) NOT NULL,
    uom_snapshot VARCHAR(20) DEFAULT NULL,
    qty DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(12,2) NOT NULL,
    unit_cost_snapshot DECIMAL(12,4) DEFAULT NULL,
    margin_amount DECIMAL(12,2) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_sale_items_line (sale_id, line_no),
    UNIQUE INDEX uq_sale_items_legacy_detail (legacy_movimiento_detalle_id),
    INDEX idx_sale_items_sale (sale_id),
    INDEX idx_sale_items_product_created (product_id, created_at),
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sale_payments (
    id BIGINT AUTO_INCREMENT NOT NULL,
    sale_id CHAR(36) NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PEN',
    tendered_amount DECIMAL(12,2) DEFAULT NULL,
    change_amount DECIMAL(12,2) DEFAULT NULL,
    authorization_code VARCHAR(100) DEFAULT NULL,
    card_brand VARCHAR(50) DEFAULT NULL,
    card_last4 VARCHAR(4) DEFAULT NULL,
    gateway_ref VARCHAR(120) DEFAULT NULL,
    wallet_ref VARCHAR(120) DEFAULT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sale_payments_sale (sale_id),
    INDEX idx_sale_payments_method_paid (payment_method_id, paid_at),
    CONSTRAINT fk_sale_payments_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_method (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sale_discounts (
    id BIGINT AUTO_INCREMENT NOT NULL,
    sale_id CHAR(36) NOT NULL,
    sale_item_id BIGINT DEFAULT NULL,
    discount_type VARCHAR(20) NOT NULL,
    source VARCHAR(20) NOT NULL,
    value DECIMAL(12,4) NOT NULL,
    amount_applied DECIMAL(12,2) NOT NULL,
    reason_code VARCHAR(30) DEFAULT NULL,
    reason_text VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sale_discounts_sale (sale_id),
    INDEX idx_sale_discounts_item (sale_item_id),
    CONSTRAINT fk_sale_discounts_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_discounts_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id) ON DELETE CASCADE,
    CONSTRAINT chk_sale_discount_type CHECK (discount_type IN ('PERCENT', 'AMOUNT', 'PROMO')),
    CONSTRAINT chk_sale_discount_source CHECK (source IN ('MANUAL', 'RULE_ENGINE', 'COUPON'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sale_taxes (
    id BIGINT AUTO_INCREMENT NOT NULL,
    sale_id CHAR(36) NOT NULL,
    sale_item_id BIGINT DEFAULT NULL,
    tax_code VARCHAR(20) NOT NULL,
    tax_rate DECIMAL(7,4) NOT NULL,
    taxable_base DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sale_taxes_sale (sale_id),
    INDEX idx_sale_taxes_item (sale_item_id),
    INDEX idx_sale_taxes_code_created (tax_code, created_at),
    CONSTRAINT fk_sale_taxes_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_taxes_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sale_taxes');
        $this->addSql('DROP TABLE sale_discounts');
        $this->addSql('DROP TABLE sale_payments');
        $this->addSql('DROP TABLE sale_items');
        $this->addSql('DROP TABLE sales');
    }
}

