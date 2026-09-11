<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Narrow indexes for the bounded retry worker. Both predicates are tenant-local
 * and the checks make this safe for installations that predate the tables.
 */
final class Version20260910000000_capacity_hot_path_indexes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add covering indexes for bounded SUNAT retry candidate and event lookups.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('tax_document') && !$this->indexExists('tax_document', 'idx_tax_document_retry_candidates')) {
            $this->addSql('CREATE INDEX idx_tax_document_retry_candidates ON tax_document (document_type, issue_date, sunat_status)');
        }

        if ($this->tableExists('tax_document_event') && !$this->indexExists('tax_document_event', 'idx_tax_document_event_retry')) {
            $this->addSql('CREATE INDEX idx_tax_document_event_retry ON tax_document_event (tax_document_id, event_type, status, created_at)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('tax_document_event', 'idx_tax_document_event_retry')) {
            $this->addSql('DROP INDEX idx_tax_document_event_retry ON tax_document_event');
        }
        if ($this->indexExists('tax_document', 'idx_tax_document_retry_candidates')) {
            $this->addSql('DROP INDEX idx_tax_document_retry_candidates ON tax_document');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1',
            ['table' => $table],
        ) !== false;
    }

    private function indexExists(string $table, string $index): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1',
            ['table' => $table, 'index' => $index],
        ) !== false;
    }
}
