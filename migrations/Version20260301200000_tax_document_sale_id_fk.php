<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds `sale_id` column to tax_document and creates a FK to sales.id.
 *
 * The column is populated from movimiento_id (same UUID in dual_write mode).
 * This establishes the canonical V2 relationship between a tax document and
 * the operational sale record in the `sales` table.
 */
final class Version20260301200000_tax_document_sale_id_fk extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tax_document.sale_id (FK → sales.id) and populate from movimiento_id.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('tax_document')) {
            return;
        }

        if (!$this->columnExists('tax_document', 'sale_id')) {
            $this->addSql('ALTER TABLE tax_document ADD COLUMN sale_id CHAR(36) NULL AFTER movimiento_id');
        }

        // Populate: in dual_write mode, sales.id = movimiento_id (same UUID).
        // Only populate where the sales row exists to avoid orphan references.
        $this->addSql(
            <<<'SQL'
UPDATE tax_document td
INNER JOIN sales s ON s.id = td.movimiento_id OR s.legacy_movimiento_id = td.movimiento_id
SET td.sale_id = s.id
WHERE td.sale_id IS NULL
SQL
        );

        if ($this->tableExists('sales') && !$this->foreignKeyExists('tax_document', 'FK_TAX_DOCUMENT_SALE')) {
            $this->addSql(
                'ALTER TABLE tax_document ADD CONSTRAINT FK_TAX_DOCUMENT_SALE FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL'
            );
        }

        if (!$this->indexExists('tax_document', 'IDX_TAX_DOCUMENT_SALE_ID')) {
            $this->addSql('CREATE INDEX IDX_TAX_DOCUMENT_SALE_ID ON tax_document (sale_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('tax_document')) {
            return;
        }

        if ($this->indexExists('tax_document', 'IDX_TAX_DOCUMENT_SALE_ID')) {
            $this->addSql('DROP INDEX IDX_TAX_DOCUMENT_SALE_ID ON tax_document');
        }

        if ($this->foreignKeyExists('tax_document', 'FK_TAX_DOCUMENT_SALE')) {
            $this->addSql('ALTER TABLE tax_document DROP FOREIGN KEY FK_TAX_DOCUMENT_SALE');
        }

        if ($this->columnExists('tax_document', 'sale_id')) {
            $this->addSql('ALTER TABLE tax_document DROP COLUMN sale_id');
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table]
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $table, 'column' => $column]
        ) > 0;
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :fk AND constraint_type = 'FOREIGN KEY'",
            ['table' => $table, 'fk' => $foreignKey]
        ) > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index',
            ['table' => $table, 'index' => $indexName]
        ) > 0;
    }
}
