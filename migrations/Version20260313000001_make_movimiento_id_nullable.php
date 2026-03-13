<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Makes tax_document.movimiento_id nullable at the DB level.
 *
 * Root cause (BUG-002): the Doctrine mapping already declares `nullable: true`
 * on the movimiento_id JoinColumn, but the physical column was created as NOT NULL.
 * This mismatch causes a NotNullConstraintViolationException when a credit note
 * (document_type = '07') is inserted in v2_authoritative mode because no legacy
 * movimiento row exists for the originating sale.
 *
 * NOTE: The down() migration reverts the column to NOT NULL. Only run it if
 * every existing tax_document row has a non-null movimiento_id; otherwise the
 * ALTER will fail. In v2_authoritative environments it is safe to leave this
 * column nullable permanently.
 */
final class Version20260313000001_make_movimiento_id_nullable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make tax_document.movimiento_id nullable to support credit notes in v2_authoritative mode (BUG-002)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_document MODIFY movimiento_id VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        // WARNING: This will fail if any tax_document row has movimiento_id = NULL.
        // Only execute down() after backfilling all NULL values.
        $this->addSql('ALTER TABLE tax_document MODIFY movimiento_id VARCHAR(255) NOT NULL');
    }
}
