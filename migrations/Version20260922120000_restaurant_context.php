<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-18: Restaurant vertical bounded context. restaurant_tables is the floor
 * plan; restaurant_orders is the accumulating comanda per dining session
 * (closed only once a real sale exists — saleId is an opaque string, no FK
 * into Sales); restaurant_order_items snapshots product name/price at add
 * time and is linked to Catalog\Product by id only, no FK.
 */
final class Version20260922120000_restaurant_context extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create restaurant_tables, restaurant_orders and restaurant_order_items (Restaurant vertical context)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE restaurant_tables (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(16) NOT NULL,
                area VARCHAR(64) NOT NULL,
                seats INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE restaurant_orders (
                id INT AUTO_INCREMENT NOT NULL,
                table_id INT NOT NULL,
                opened_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                sale_id VARCHAR(64) DEFAULT NULL,
                INDEX idx_restaurant_order_table (table_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE restaurant_order_items (
                id INT AUTO_INCREMENT NOT NULL,
                order_id INT NOT NULL,
                product_id VARCHAR(64) NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                unit_price VARCHAR(20) NOT NULL,
                qty INT NOT NULL,
                notes VARCHAR(500) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                ready_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_restaurant_order_item_order (order_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant_order_items
                ADD CONSTRAINT FK_restaurant_order_items_order
                FOREIGN KEY (order_id) REFERENCES restaurant_orders (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_order_items DROP FOREIGN KEY FK_restaurant_order_items_order');
        $this->addSql('DROP TABLE restaurant_order_items');
        $this->addSql('DROP TABLE restaurant_orders');
        $this->addSql('DROP TABLE restaurant_tables');
    }
}
