<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the legacy movimiento and movimiento_detalle tables.
 *
 * Historical data has been fully migrated to the sales (SaleRecord) table.
 * The movimiento_id column on tax_document is intentionally retained as it
 * holds historical FK references and was made nullable in Version20260313000001.
 *
 * down() is intentionally empty — dropping movimiento is irreversible and
 * the data migration to the sales table cannot be automatically undone.
 */
final class Version20260314000001_drop_movimiento extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy movimiento and movimiento_detalle tables (data migrated to sales/SaleRecord)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS movimiento_detalle');
        $this->addSql('DROP TABLE IF EXISTS movimiento');
    }

    public function down(Schema $schema): void
    {
        // Intentionally left empty — dropping movimiento is irreversible.
        // The data migration to the `sales` (SaleRecord) table cannot be automatically undone.
    }
}
