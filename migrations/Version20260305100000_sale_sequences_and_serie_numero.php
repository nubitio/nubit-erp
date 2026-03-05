<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260305100000_sale_sequences_and_serie_numero extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sale_sequences table for serie/numero generation and add serie + numero columns with index to sales table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE sale_sequences (
    serie VARCHAR(10) NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (serie)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE sales
    ADD COLUMN serie VARCHAR(10) NULL AFTER legacy_movimiento_id,
    ADD COLUMN numero VARCHAR(20) NULL AFTER serie,
    ADD INDEX idx_sales_serie_numero (serie, numero)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE sales
    DROP INDEX idx_sales_serie_numero,
    DROP COLUMN numero,
    DROP COLUMN serie
SQL);

        $this->addSql('DROP TABLE sale_sequences');
    }
}
