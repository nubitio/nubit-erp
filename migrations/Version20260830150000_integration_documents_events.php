<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Integration document intake + the transactional outbox for webhook events.
 */
final class Version20260830150000_integration_documents_events extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create integration_document and integration_event tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_document (
                id VARCHAR(255) NOT NULL,
                external_id VARCHAR(64) NOT NULL,
                reference VARCHAR(40) NOT NULL,
                request_hash VARCHAR(64) NOT NULL,
                request_payload JSON NOT NULL,
                document_type VARCHAR(10) NOT NULL,
                series VARCHAR(10) NOT NULL,
                number INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                tax_document_id VARCHAR(40) DEFAULT NULL,
                sunat_status VARCHAR(50) DEFAULT NULL,
                cdr_reference VARCHAR(60) DEFAULT NULL,
                error LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                UNIQUE INDEX uq_integration_document_external_id (external_id),
                UNIQUE INDEX uq_integration_document_reference (reference)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE integration_event (
                id VARCHAR(255) NOT NULL,
                type VARCHAR(60) NOT NULL,
                resource_type VARCHAR(30) NOT NULL,
                resource_id VARCHAR(64) NOT NULL,
                payload JSON NOT NULL,
                status VARCHAR(20) NOT NULL,
                occurred_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                dispatched_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                INDEX idx_integration_event_status (status, occurred_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_event');
        $this->addSql('DROP TABLE integration_document');
    }
}
