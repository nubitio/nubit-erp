<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the price_list_detail table for per-product price overrides.
 *
 * PriceListProvider and PriceListProcessor both reference this table via raw SQL
 * but it was never included in a migration, causing runtime failures.
 *
 * Schema:
 *   - product_id  → FK to producto.producto_id  (VARCHAR 255)
 *   - price_list_id → FK to price_list.id       (INT)
 *   - price        DECIMAL(12,4) NOT NULL        exact decimal, 4dp for currency
 *
 * The UNIQUE key on (product_id, price_list_id) enables ON DUPLICATE KEY UPDATE
 * in PriceListProcessor without creating duplicate rows.
 */
final class Version20260619000001_price_list_detail extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create price_list_detail table for per-product price overrides';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE price_list_detail (
                id          INT AUTO_INCREMENT NOT NULL,
                product_id  VARCHAR(255)       NOT NULL,
                price_list_id INT             NOT NULL,
                price       DECIMAL(12,4)      NOT NULL,
                updated_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_product_price_list (product_id, price_list_id),
                INDEX IDX_pld_product (product_id),
                INDEX IDX_pld_price_list (price_list_id),
                CONSTRAINT FK_pld_product
                    FOREIGN KEY (product_id)  REFERENCES producto (producto_id) ON DELETE CASCADE,
                CONSTRAINT FK_pld_price_list
                    FOREIGN KEY (price_list_id) REFERENCES price_list (id)      ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS price_list_detail');
    }
}
