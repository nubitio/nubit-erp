<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814194800_purchase_orders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purchase orders: draft/sent/partially_received/received/cancelled orders with items, '
            . 'plus additive nullable links from the existing goods_receipts/goods_receipt_items tables '
            . '(direct stock-intake flow keeps working unchanged when those links are null)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_orders (
                id CHAR(36) NOT NULL,
                order_no INT DEFAULT NULL,
                branch_id CHAR(36) DEFAULT NULL,
                warehouse_id CHAR(36) DEFAULT NULL,
                supplier_id CHAR(36) NOT NULL,
                buyer_user_id INT DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'draft' NOT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                subtotal NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                tax_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                grand_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                note LONGTEXT DEFAULT NULL,
                expected_date DATE DEFAULT NULL,
                issued_at DATETIME NOT NULL,
                sent_at DATETIME DEFAULT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX idx_purchase_orders_status_issued (status, issued_at),
                INDEX idx_purchase_orders_supplier (supplier_id),
                INDEX idx_purchase_orders_branch (branch_id),
                INDEX IDX_PURCHASE_ORDERS_WAREHOUSE (warehouse_id),
                INDEX IDX_PURCHASE_ORDERS_BUYER (buyer_user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_order_items (
                id INT AUTO_INCREMENT NOT NULL,
                purchase_order_id CHAR(36) NOT NULL,
                line_no INT NOT NULL,
                product_id CHAR(36) DEFAULT NULL,
                name_snapshot VARCHAR(255) NOT NULL,
                sku_snapshot VARCHAR(64) DEFAULT NULL,
                uom_snapshot VARCHAR(20) DEFAULT NULL,
                ordered_qty NUMERIC(14, 3) NOT NULL,
                received_qty NUMERIC(14, 3) DEFAULT '0.000' NOT NULL,
                expected_unit_cost NUMERIC(12, 4) NOT NULL,
                gross_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                tax_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                total_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                INDEX idx_purchase_order_items_order (purchase_order_id),
                INDEX IDX_PURCHASE_ORDER_ITEMS_PRODUCT (product_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PURCHASE_ORDERS_BRANCH FOREIGN KEY (branch_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PURCHASE_ORDERS_WAREHOUSE FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id)');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PURCHASE_ORDERS_SUPPLIER FOREIGN KEY (supplier_id) REFERENCES proveedor (proveedor_id)');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PURCHASE_ORDERS_BUYER FOREIGN KEY (buyer_user_id) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT FK_PURCHASE_ORDER_ITEMS_ORDER FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id)');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT FK_PURCHASE_ORDER_ITEMS_PRODUCT FOREIGN KEY (product_id) REFERENCES producto (producto_id)');

        $this->addSql('ALTER TABLE goods_receipts ADD purchase_order_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE goods_receipts ADD CONSTRAINT FK_GOODS_RECEIPTS_PURCHASE_ORDER FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id)');
        $this->addSql('CREATE INDEX IDX_GOODS_RECEIPTS_PURCHASE_ORDER ON goods_receipts (purchase_order_id)');

        $this->addSql('ALTER TABLE goods_receipt_items ADD purchase_order_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE goods_receipt_items ADD CONSTRAINT FK_GOODS_RECEIPT_ITEMS_PURCHASE_ORDER_ITEM FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id)');
        $this->addSql('CREATE INDEX IDX_GOODS_RECEIPT_ITEMS_PURCHASE_ORDER_ITEM ON goods_receipt_items (purchase_order_item_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_order_sequences (
                id INT NOT NULL,
                last_number INT DEFAULT 0 NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE purchase_order_sequences');

        $this->addSql('ALTER TABLE goods_receipt_items DROP FOREIGN KEY FK_GOODS_RECEIPT_ITEMS_PURCHASE_ORDER_ITEM');
        $this->addSql('DROP INDEX IDX_GOODS_RECEIPT_ITEMS_PURCHASE_ORDER_ITEM ON goods_receipt_items');
        $this->addSql('ALTER TABLE goods_receipt_items DROP purchase_order_item_id');

        $this->addSql('ALTER TABLE goods_receipts DROP FOREIGN KEY FK_GOODS_RECEIPTS_PURCHASE_ORDER');
        $this->addSql('DROP INDEX IDX_GOODS_RECEIPTS_PURCHASE_ORDER ON goods_receipts');
        $this->addSql('ALTER TABLE goods_receipts DROP purchase_order_id');

        $this->addSql('ALTER TABLE purchase_order_items DROP FOREIGN KEY FK_PURCHASE_ORDER_ITEMS_ORDER');
        $this->addSql('ALTER TABLE purchase_order_items DROP FOREIGN KEY FK_PURCHASE_ORDER_ITEMS_PRODUCT');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_PURCHASE_ORDERS_BRANCH');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_PURCHASE_ORDERS_WAREHOUSE');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_PURCHASE_ORDERS_SUPPLIER');
        $this->addSql('ALTER TABLE purchase_orders DROP FOREIGN KEY FK_PURCHASE_ORDERS_BUYER');
        $this->addSql('DROP TABLE purchase_order_items');
        $this->addSql('DROP TABLE purchase_orders');
    }
}
