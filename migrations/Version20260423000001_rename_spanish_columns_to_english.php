<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename Spanish column names to English:
 *   sales.serie       → sales.series
 *   sales.numero      → sales.number
 *   sale_sequences.serie → sale_sequences.series
 *   local_comprobante.serie → local_comprobante.series
 */
final class Version20260423000001_rename_spanish_columns_to_english extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename Spanish column names (serie, numero) to English (series, number)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales RENAME COLUMN serie TO series');
        $this->addSql('ALTER TABLE sales RENAME COLUMN numero TO number');
        $this->addSql('ALTER TABLE sale_sequences RENAME COLUMN serie TO series');
        $this->addSql('ALTER TABLE local_comprobante RENAME COLUMN serie TO series');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales RENAME COLUMN series TO serie');
        $this->addSql('ALTER TABLE sales RENAME COLUMN number TO numero');
        $this->addSql('ALTER TABLE sale_sequences RENAME COLUMN series TO serie');
        $this->addSql('ALTER TABLE local_comprobante RENAME COLUMN series TO serie');
    }
}
