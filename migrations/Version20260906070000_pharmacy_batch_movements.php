<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-12: append-only record of which pharmacy batch a sale drew down (FEFO),
 * written by the SaleConfirmed handler. No cross-context FK.
 */
final class Version20260906070000_pharmacy_batch_movements extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pharmacy_batch_movements (batch drawdown ledger)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmacy_batch_movements (
                id INT AUTO_INCREMENT NOT NULL,
                batch_id INT NOT NULL,
                product_id INT NOT NULL,
                sale_id VARCHAR(255) NOT NULL,
                quantity VARCHAR(32) NOT NULL,
                occurred_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_pharmacy_batch_movement_batch (batch_id),
                INDEX idx_pharmacy_batch_movement_sale (sale_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pharmacy_batch_movements');
    }
}
