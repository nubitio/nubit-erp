<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the foreign key constraint from tax_document.movimiento_id to movimiento.movimiento_id.
 *
 * In v2_authoritative sales mode, invoices are linked to a sale whose UUID exists only in the
 * `sales` table and never in `movimiento`. The movimiento_id column retains its semantics
 * (sale/movement reference) but is no longer DB-enforced to allow both legacy and V2 paths.
 */
final class Version20260301100000_drop_tax_document_movimiento_fk extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop FK constraint tax_document.movimiento_id → movimiento to support v2_authoritative invoicing.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('tax_document') && $this->foreignKeyExists('tax_document', 'FK_TAX_DOCUMENT_MOVIMIENTO')) {
            $this->addSql('ALTER TABLE tax_document DROP FOREIGN KEY FK_TAX_DOCUMENT_MOVIMIENTO');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('tax_document') && $this->tableExists('movimiento') && !$this->foreignKeyExists('tax_document', 'FK_TAX_DOCUMENT_MOVIMIENTO')) {
            $this->addSql('ALTER TABLE tax_document ADD CONSTRAINT FK_TAX_DOCUMENT_MOVIMIENTO FOREIGN KEY (movimiento_id) REFERENCES movimiento (movimiento_id) ON DELETE CASCADE');
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table]
        ) > 0;
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :fk AND constraint_type = 'FOREIGN KEY'",
            ['table' => $table, 'fk' => $foreignKey]
        ) > 0;
    }
}
