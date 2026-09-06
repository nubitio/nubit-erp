<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-10: first vertical bounded context. pharmacy_products carries the
 * pharmacy-specific facts about a catalogue product (linked by product_id only,
 * no FK — the Catalog `producto` table is untouched); pharmacy_batches holds
 * batch metadata (number, expiry, manufacturer) — quantity stays in Inventory.
 */
final class Version20260906060000_pharmacy_context extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pharmacy_products and pharmacy_batches (Pharmacy vertical context)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmacy_products (
                id INT AUTO_INCREMENT NOT NULL,
                product_id INT NOT NULL,
                active_ingredient VARCHAR(255) DEFAULT NULL,
                sanitary_registration VARCHAR(64) DEFAULT NULL,
                requires_prescription TINYINT(1) NOT NULL,
                controlled TINYINT(1) NOT NULL,
                expiry_control VARCHAR(10) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_pharmacy_product (product_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE pharmacy_batches (
                id INT AUTO_INCREMENT NOT NULL,
                product_id INT NOT NULL,
                batch_number VARCHAR(64) NOT NULL,
                expiration_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                manufacturer VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_pharmacy_batch (product_id, batch_number),
                INDEX idx_pharmacy_batch_expiry (expiration_date),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pharmacy_batches');
        $this->addSql('DROP TABLE pharmacy_products');
    }
}
