<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227143000_sales_v2_phase2_ops extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sales V2 phase 2: returns, stock ledger, cash sessions v2, offline sync, audit trail and daily summaries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE returns (
    id CHAR(36) NOT NULL,
    sale_id CHAR(36) NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    cash_session_id INT DEFAULT NULL,
    customer_id INT DEFAULT NULL,
    seller_user_id INT NOT NULL,
    reason_code VARCHAR(30) DEFAULT NULL,
    reason_text VARCHAR(255) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'COMPLETED',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    refund_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    local_uuid CHAR(36) DEFAULT NULL,
    idempotency_key CHAR(36) DEFAULT NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    synced_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_returns_local_uuid (local_uuid),
    UNIQUE INDEX uq_returns_idempotency_key (idempotency_key),
    INDEX idx_returns_sale_created (sale_id, created_at),
    INDEX idx_returns_branch_created (branch_id, created_at),
    INDEX idx_returns_status_created (status, created_at),
    CONSTRAINT fk_returns_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
    CONSTRAINT fk_returns_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_returns_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_returns_cash_session FOREIGN KEY (cash_session_id) REFERENCES cash_shift (id),
    CONSTRAINT fk_returns_customer FOREIGN KEY (customer_id) REFERENCES cliente (id),
    CONSTRAINT fk_returns_seller FOREIGN KEY (seller_user_id) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_returns_status CHECK (status IN ('DRAFT', 'COMPLETED', 'VOIDED')),
    CONSTRAINT chk_returns_sync_status CHECK (sync_status IN ('PENDING', 'SYNCING', 'SYNCED', 'ERROR', 'CONFLICT'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE return_items (
    id BIGINT AUTO_INCREMENT NOT NULL,
    return_id CHAR(36) NOT NULL,
    sale_item_id BIGINT DEFAULT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) DEFAULT NULL,
    qty_returned DECIMAL(14,3) NOT NULL,
    unit_price_snapshot DECIMAL(12,4) NOT NULL,
    taxed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    reason_code VARCHAR(30) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_return_items_return (return_id),
    INDEX idx_return_items_sale_item (sale_item_id),
    INDEX idx_return_items_product_created (product_id, created_at),
    CONSTRAINT fk_return_items_return FOREIGN KEY (return_id) REFERENCES returns (id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_sale_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id),
    CONSTRAINT fk_return_items_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE refund_payments (
    id BIGINT AUTO_INCREMENT NOT NULL,
    return_id CHAR(36) NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PEN',
    gateway_ref VARCHAR(120) DEFAULT NULL,
    authorization_code VARCHAR(100) DEFAULT NULL,
    refunded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_refund_payments_return (return_id),
    INDEX idx_refund_payments_method_refunded (payment_method_id, refunded_at),
    CONSTRAINT fk_refund_payments_return FOREIGN KEY (return_id) REFERENCES returns (id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_method (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE stock_transfers (
    id CHAR(36) NOT NULL,
    from_warehouse_id VARCHAR(255) NOT NULL,
    to_warehouse_id VARCHAR(255) NOT NULL,
    requested_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    received_by INT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    received_at DATETIME DEFAULT NULL,
    reference_code VARCHAR(50) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_stock_transfers_status_requested (status, requested_at),
    INDEX idx_stock_transfers_from_created (from_warehouse_id, created_at),
    INDEX idx_stock_transfers_to_created (to_warehouse_id, created_at),
    CONSTRAINT fk_stock_transfers_from_warehouse FOREIGN KEY (from_warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_stock_transfers_to_warehouse FOREIGN KEY (to_warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_stock_transfers_requested_by FOREIGN KEY (requested_by) REFERENCES usuario (usuario_id),
    CONSTRAINT fk_stock_transfers_approved_by FOREIGN KEY (approved_by) REFERENCES usuario (usuario_id),
    CONSTRAINT fk_stock_transfers_received_by FOREIGN KEY (received_by) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_stock_transfers_status CHECK (status IN ('DRAFT', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE stock_transfer_items (
    id BIGINT AUTO_INCREMENT NOT NULL,
    stock_transfer_id CHAR(36) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) DEFAULT NULL,
    qty_requested DECIMAL(14,3) NOT NULL,
    qty_sent DECIMAL(14,3) DEFAULT NULL,
    qty_received DECIMAL(14,3) DEFAULT NULL,
    unit_cost_snapshot DECIMAL(12,4) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_stock_transfer_items_line (stock_transfer_id, product_id, variant_id),
    INDEX idx_stock_transfer_items_transfer (stock_transfer_id),
    INDEX idx_stock_transfer_items_product_created (product_id, created_at),
    CONSTRAINT fk_stock_transfer_items_transfer FOREIGN KEY (stock_transfer_id) REFERENCES stock_transfers (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_transfer_items_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE stock_movements (
    id BIGINT AUTO_INCREMENT NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) DEFAULT NULL,
    occurred_at DATETIME NOT NULL,
    movement_type VARCHAR(30) NOT NULL,
    quantity_delta DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(12,4) DEFAULT NULL,
    total_cost DECIMAL(12,2) DEFAULT NULL,
    source_type VARCHAR(30) NOT NULL,
    source_id VARCHAR(64) DEFAULT NULL,
    sale_item_id BIGINT DEFAULT NULL,
    return_item_id BIGINT DEFAULT NULL,
    stock_transfer_item_id BIGINT DEFAULT NULL,
    reference_code VARCHAR(50) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    local_uuid CHAR(36) DEFAULT NULL,
    idempotency_key CHAR(36) DEFAULT NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
    synced_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_stock_movements_local_uuid (local_uuid),
    UNIQUE INDEX uq_stock_movements_idempotency (idempotency_key),
    INDEX idx_stock_movements_warehouse_product_occurred (warehouse_id, product_id, occurred_at),
    INDEX idx_stock_movements_source (source_type, source_id),
    INDEX idx_stock_movements_product_created (product_id, created_at),
    INDEX idx_stock_movements_type_occurred (movement_type, occurred_at),
    CONSTRAINT fk_stock_movements_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES producto (producto_id),
    CONSTRAINT fk_stock_movements_sale_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id),
    CONSTRAINT fk_stock_movements_return_item FOREIGN KEY (return_item_id) REFERENCES return_items (id),
    CONSTRAINT fk_stock_movements_transfer_item FOREIGN KEY (stock_transfer_item_id) REFERENCES stock_transfer_items (id),
    CONSTRAINT chk_stock_movements_type CHECK (movement_type IN (
        'SALE_OUT',
        'RETURN_IN',
        'PURCHASE_IN',
        'TRANSFER_OUT',
        'TRANSFER_IN',
        'ADJUSTMENT_POS',
        'ADJUSTMENT_NEG',
        'WASTE_OUT'
    )),
    CONSTRAINT chk_stock_movements_sync_status CHECK (sync_status IN ('PENDING', 'SYNCING', 'SYNCED', 'ERROR', 'CONFLICT'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE stock_balances (
    warehouse_id VARCHAR(255) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    variant_id VARCHAR(255) NOT NULL DEFAULT '',
    on_hand_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    reserved_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    available_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    avg_cost DECIMAL(12,4) DEFAULT NULL,
    last_movement_at DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (warehouse_id, product_id, variant_id),
    INDEX idx_stock_balances_product_updated (product_id, updated_at),
    CONSTRAINT fk_stock_balances_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_stock_balances_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE cash_sessions (
    id INT AUTO_INCREMENT NOT NULL,
    legacy_cash_shift_id INT DEFAULT NULL,
    cash_register_id INT NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    opened_by INT NOT NULL,
    closed_by INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_amount DECIMAL(12,2) DEFAULT NULL,
    counted_amount DECIMAL(12,2) DEFAULT NULL,
    difference_amount DECIMAL(12,2) DEFAULT NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    close_note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_cash_sessions_legacy_cash_shift (legacy_cash_shift_id),
    INDEX idx_cash_sessions_register_opened (cash_register_id, opened_at),
    INDEX idx_cash_sessions_branch_opened (branch_id, opened_at),
    INDEX idx_cash_sessions_status_opened (status, opened_at),
    CONSTRAINT fk_cash_sessions_legacy_cash_shift FOREIGN KEY (legacy_cash_shift_id) REFERENCES cash_shift (id),
    CONSTRAINT fk_cash_sessions_register FOREIGN KEY (cash_register_id) REFERENCES cash_register (id),
    CONSTRAINT fk_cash_sessions_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_cash_sessions_opened_by FOREIGN KEY (opened_by) REFERENCES usuario (usuario_id),
    CONSTRAINT fk_cash_sessions_closed_by FOREIGN KEY (closed_by) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_cash_sessions_status CHECK (status IN ('OPEN', 'CLOSED', 'RECONCILING'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE cash_movements (
    id BIGINT AUTO_INCREMENT NOT NULL,
    cash_session_id INT NOT NULL,
    movement_type VARCHAR(30) NOT NULL,
    category_id INT DEFAULT NULL,
    sale_id CHAR(36) DEFAULT NULL,
    return_id CHAR(36) DEFAULT NULL,
    payment_method_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cash_movements_session_created (cash_session_id, created_at),
    INDEX idx_cash_movements_type_created (movement_type, created_at),
    INDEX idx_cash_movements_sale (sale_id),
    INDEX idx_cash_movements_return (return_id),
    CONSTRAINT fk_cash_movements_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cash_movements_category FOREIGN KEY (category_id) REFERENCES cash_movement_category (id),
    CONSTRAINT fk_cash_movements_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
    CONSTRAINT fk_cash_movements_return FOREIGN KEY (return_id) REFERENCES returns (id),
    CONSTRAINT fk_cash_movements_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_method (id),
    CONSTRAINT fk_cash_movements_created_by FOREIGN KEY (created_by) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_cash_movements_type CHECK (movement_type IN (
        'OPENING',
        'SALE_IN',
        'REFUND_OUT',
        'CASH_IN',
        'CASH_OUT',
        'CLOSING_ADJUSTMENT'
    ))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE cash_counts (
    id BIGINT AUTO_INCREMENT NOT NULL,
    cash_session_id INT NOT NULL,
    count_stage VARCHAR(20) NOT NULL,
    denomination_type VARCHAR(10) NOT NULL,
    denomination_value DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    counted_by INT NOT NULL,
    counted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cash_counts_session_stage (cash_session_id, count_stage),
    CONSTRAINT fk_cash_counts_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_cash_counts_counted_by FOREIGN KEY (counted_by) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_cash_counts_stage CHECK (count_stage IN ('OPEN', 'CLOSE', 'RECOUNT')),
    CONSTRAINT chk_cash_counts_type CHECK (denomination_type IN ('BILL', 'COIN'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sync_inbox (
    id BIGINT AUTO_INCREMENT NOT NULL,
    message_id CHAR(36) NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    operation VARCHAR(20) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_sync_inbox_message_id (message_id),
    INDEX idx_sync_inbox_device_received (device_id, received_at),
    INDEX idx_sync_inbox_status_received (status, received_at),
    CONSTRAINT chk_sync_inbox_operation CHECK (operation IN ('UPSERT', 'DELETE')),
    CONSTRAINT chk_sync_inbox_status CHECK (status IN ('PENDING', 'PROCESSED', 'ERROR', 'CONFLICT'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sync_outbox_server (
    id BIGINT AUTO_INCREMENT NOT NULL,
    message_id CHAR(36) NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    operation VARCHAR(20) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    attempts INT NOT NULL DEFAULT 0,
    last_error VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_sync_outbox_message_id (message_id),
    INDEX idx_sync_outbox_device_available (device_id, available_at),
    INDEX idx_sync_outbox_status_available (status, available_at),
    CONSTRAINT chk_sync_outbox_operation CHECK (operation IN ('UPSERT', 'DELETE')),
    CONSTRAINT chk_sync_outbox_status CHECK (status IN ('PENDING', 'SENT', 'ACKED', 'ERROR'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE conflict_resolutions (
    id BIGINT AUTO_INCREMENT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    server_version INT NOT NULL,
    client_version INT NOT NULL,
    resolution_strategy VARCHAR(30) NOT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_conflict_entity_resolved (entity_type, entity_id, resolved_at),
    INDEX idx_conflict_device_resolved (device_id, resolved_at),
    CONSTRAINT fk_conflict_resolved_by FOREIGN KEY (resolved_by) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_conflict_resolution_strategy CHECK (resolution_strategy IN ('SERVER_WINS', 'CLIENT_WINS', 'MERGED', 'MANUAL'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE audit_log (
    id BIGINT AUTO_INCREMENT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    action VARCHAR(30) NOT NULL,
    actor_user_id INT DEFAULT NULL,
    request_id CHAR(36) DEFAULT NULL,
    context_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_audit_entity_created (entity_type, entity_id, created_at),
    INDEX idx_audit_actor_created (actor_user_id, created_at),
    INDEX idx_audit_action_created (action, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES usuario (usuario_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE price_history (
    id BIGINT AUTO_INCREMENT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    price_type VARCHAR(20) NOT NULL,
    old_price DECIMAL(12,4) NOT NULL,
    new_price DECIMAL(12,4) NOT NULL,
    changed_by INT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_price_history_product_changed (product_id, changed_at),
    INDEX idx_price_history_type_changed (price_type, changed_at),
    CONSTRAINT fk_price_history_product FOREIGN KEY (product_id) REFERENCES producto (producto_id),
    CONSTRAINT fk_price_history_changed_by FOREIGN KEY (changed_by) REFERENCES usuario (usuario_id),
    CONSTRAINT chk_price_history_type CHECK (price_type IN ('SALE', 'PURCHASE'))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE entity_versions (
    id BIGINT AUTO_INCREMENT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    version_no INT NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX uq_entity_versions_no (entity_type, entity_id, version_no),
    INDEX idx_entity_versions_entity_created (entity_type, entity_id, created_at),
    CONSTRAINT fk_entity_versions_created_by FOREIGN KEY (created_by) REFERENCES usuario (usuario_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE sales_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    sales_count INT NOT NULL DEFAULT 0,
    gross_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    avg_ticket DECIMAL(14,2) NOT NULL DEFAULT 0,
    items_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id, warehouse_id),
    INDEX idx_sales_daily_branch_date (branch_id, summary_date),
    INDEX idx_sales_daily_warehouse_date (warehouse_id, summary_date),
    CONSTRAINT fk_sales_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_sales_daily_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE product_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    qty_sold DECIMAL(14,3) NOT NULL DEFAULT 0,
    net_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    cost_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    margin_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    returns_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id, warehouse_id, product_id),
    INDEX idx_product_daily_branch_date (branch_id, summary_date),
    INDEX idx_product_daily_product_date (product_id, summary_date),
    CONSTRAINT fk_product_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_product_daily_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_product_daily_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE cashier_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    cashier_user_id INT NOT NULL,
    cash_session_id INT NOT NULL,
    sales_count INT NOT NULL DEFAULT 0,
    collected_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    refund_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    cash_difference DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id, cashier_user_id, cash_session_id),
    INDEX idx_cashier_daily_branch_date (branch_id, summary_date),
    INDEX idx_cashier_daily_user_date (cashier_user_id, summary_date),
    CONSTRAINT fk_cashier_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_cashier_daily_user FOREIGN KEY (cashier_user_id) REFERENCES usuario (usuario_id),
    CONSTRAINT fk_cashier_daily_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE branch_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    sales_count INT NOT NULL DEFAULT 0,
    gross_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    refunds_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    cash_difference_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id),
    INDEX idx_branch_daily_date (summary_date),
    CONSTRAINT fk_branch_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE payment_method_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    payment_method_id INT NOT NULL,
    payment_count INT NOT NULL DEFAULT 0,
    amount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    refund_amount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id, payment_method_id),
    INDEX idx_payment_daily_branch_date (branch_id, summary_date),
    CONSTRAINT fk_payment_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_payment_daily_method FOREIGN KEY (payment_method_id) REFERENCES payment_method (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment_method_daily_summary');
        $this->addSql('DROP TABLE branch_daily_summary');
        $this->addSql('DROP TABLE cashier_daily_summary');
        $this->addSql('DROP TABLE product_daily_summary');
        $this->addSql('DROP TABLE sales_daily_summary');
        $this->addSql('DROP TABLE entity_versions');
        $this->addSql('DROP TABLE price_history');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE conflict_resolutions');
        $this->addSql('DROP TABLE sync_outbox_server');
        $this->addSql('DROP TABLE sync_inbox');
        $this->addSql('DROP TABLE cash_counts');
        $this->addSql('DROP TABLE cash_movements');
        $this->addSql('DROP TABLE cash_sessions');
        $this->addSql('DROP TABLE stock_balances');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('DROP TABLE stock_transfer_items');
        $this->addSql('DROP TABLE stock_transfers');
        $this->addSql('DROP TABLE refund_payments');
        $this->addSql('DROP TABLE return_items');
        $this->addSql('DROP TABLE returns');
    }
}

