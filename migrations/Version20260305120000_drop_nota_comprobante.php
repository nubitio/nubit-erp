<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the legacy `comprobante` and `nota` tables.
 *
 * These tables were part of the original e-invoicing model and have been superseded
 * by `tax_document` in the unified schema (SLICE-015). Both tables are dropped
 * unconditionally; there is no rollback — data loss is intentional.
 */
final class Version20260305120000DropNotaComprobante extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SLICE-015: Drop legacy comprobante and nota tables — superseded by tax_document. Irreversible.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS comprobante');
        $this->addSql('DROP TABLE IF EXISTS nota');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Dropping nota and comprobante is intentional and irreversible.');
    }
}
