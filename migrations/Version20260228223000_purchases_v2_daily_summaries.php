<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228223000_purchases_v2_daily_summaries extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purchases V2 phase 3: purchases read models for fast daily reporting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE purchases_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    receipts_count INT NOT NULL DEFAULT 0,
    suppliers_count INT NOT NULL DEFAULT 0,
    items_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id, warehouse_id),
    INDEX idx_purchases_daily_branch_date (branch_id, summary_date),
    INDEX idx_purchases_daily_warehouse_date (warehouse_id, summary_date),
    CONSTRAINT fk_purchases_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_purchases_daily_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE purchase_product_daily_summary (
    summary_date DATE NOT NULL,
    branch_id VARCHAR(255) NOT NULL,
    warehouse_id VARCHAR(255) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    receipts_count INT NOT NULL DEFAULT 0,
    qty_received DECIMAL(14,3) NOT NULL DEFAULT 0,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (summary_date, branch_id, warehouse_id, product_id),
    INDEX idx_purchase_product_daily_branch_date (branch_id, summary_date),
    INDEX idx_purchase_product_daily_product_date (product_id, summary_date),
    CONSTRAINT fk_purchase_product_daily_branch FOREIGN KEY (branch_id) REFERENCES local (local_id),
    CONSTRAINT fk_purchase_product_daily_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id),
    CONSTRAINT fk_purchase_product_daily_product FOREIGN KEY (product_id) REFERENCES producto (producto_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE purchase_product_daily_summary');
        $this->addSql('DROP TABLE purchases_daily_summary');
    }
}
