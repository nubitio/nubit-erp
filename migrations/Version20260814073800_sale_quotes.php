<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814073800_sale_quotes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sales quotes: pre-sale quotes with items and a convert-to-sale link, '
            . 'purely additive — no changes to sales/sale_items or the existing write path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sale_quotes (
                id CHAR(36) NOT NULL,
                branch_id CHAR(36) DEFAULT NULL,
                warehouse_id CHAR(36) DEFAULT NULL,
                customer_id INT DEFAULT NULL,
                seller_user_id INT DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'draft' NOT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                subtotal NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                discount_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                tax_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                grand_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                issued_at DATETIME NOT NULL,
                valid_until DATE DEFAULT NULL,
                note LONGTEXT DEFAULT NULL,
                converted_sale_id CHAR(36) DEFAULT NULL,
                converted_at DATETIME DEFAULT NULL,
                created_by INT DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_SALE_QUOTES_CONVERTED_SALE (converted_sale_id),
                INDEX idx_sale_quotes_status_issued (status, issued_at),
                INDEX idx_sale_quotes_customer (customer_id),
                INDEX idx_sale_quotes_valid_until (valid_until),
                INDEX idx_sale_quotes_branch (branch_id),
                INDEX IDX_SALE_QUOTES_WAREHOUSE (warehouse_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sale_quote_items (
                id INT AUTO_INCREMENT NOT NULL,
                sale_quote_id CHAR(36) NOT NULL,
                line_no INT NOT NULL,
                product_id CHAR(36) DEFAULT NULL,
                qty NUMERIC(14, 3) NOT NULL,
                unit_price NUMERIC(12, 4) NOT NULL,
                discount_percent NUMERIC(8, 4) DEFAULT NULL,
                gross_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                discount_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                tax_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                net_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                name_snapshot VARCHAR(255) NOT NULL,
                sku_snapshot VARCHAR(64) DEFAULT NULL,
                uom_snapshot VARCHAR(20) DEFAULT NULL,
                INDEX idx_sale_quote_items_quote (sale_quote_id),
                INDEX IDX_SALE_QUOTE_ITEMS_PRODUCT (product_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE sale_quotes ADD CONSTRAINT FK_SALE_QUOTES_BRANCH FOREIGN KEY (branch_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE sale_quotes ADD CONSTRAINT FK_SALE_QUOTES_WAREHOUSE FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id)');
        $this->addSql('ALTER TABLE sale_quotes ADD CONSTRAINT FK_SALE_QUOTES_CUSTOMER FOREIGN KEY (customer_id) REFERENCES cliente (id)');
        $this->addSql('ALTER TABLE sale_quotes ADD CONSTRAINT FK_SALE_QUOTES_CONVERTED_SALE FOREIGN KEY (converted_sale_id) REFERENCES sales (id)');
        $this->addSql('ALTER TABLE sale_quote_items ADD CONSTRAINT FK_SALE_QUOTE_ITEMS_QUOTE FOREIGN KEY (sale_quote_id) REFERENCES sale_quotes (id)');
        $this->addSql('ALTER TABLE sale_quote_items ADD CONSTRAINT FK_SALE_QUOTE_ITEMS_PRODUCT FOREIGN KEY (product_id) REFERENCES producto (producto_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_quote_items DROP FOREIGN KEY FK_SALE_QUOTE_ITEMS_QUOTE');
        $this->addSql('ALTER TABLE sale_quote_items DROP FOREIGN KEY FK_SALE_QUOTE_ITEMS_PRODUCT');
        $this->addSql('ALTER TABLE sale_quotes DROP FOREIGN KEY FK_SALE_QUOTES_BRANCH');
        $this->addSql('ALTER TABLE sale_quotes DROP FOREIGN KEY FK_SALE_QUOTES_WAREHOUSE');
        $this->addSql('ALTER TABLE sale_quotes DROP FOREIGN KEY FK_SALE_QUOTES_CUSTOMER');
        $this->addSql('ALTER TABLE sale_quotes DROP FOREIGN KEY FK_SALE_QUOTES_CONVERTED_SALE');
        $this->addSql('DROP TABLE sale_quote_items');
        $this->addSql('DROP TABLE sale_quotes');
    }
}
