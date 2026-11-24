<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124161000_integration_sales_intake extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scope commercial API clients to one branch and reserve idempotency keys for sale intake';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE integration_api_client
                ADD sales_branch_id VARCHAR(255) DEFAULT NULL,
                ADD INDEX IDX_INTEGRATION_API_CLIENT_SALES_BRANCH (sales_branch_id),
                ADD CONSTRAINT FK_INTEGRATION_API_CLIENT_SALES_BRANCH FOREIGN KEY (sales_branch_id)
                    REFERENCES local (local_id) ON DELETE RESTRICT
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_sales_request (
                client_id VARCHAR(255) NOT NULL,
                idempotency_key VARBINARY(128) NOT NULL,
                branch_id VARCHAR(255) NOT NULL,
                payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                sale_id CHAR(36) DEFAULT NULL,
                PRIMARY KEY (client_id, idempotency_key),
                UNIQUE INDEX UQ_INTEGRATION_SALES_REQUEST_SALE (sale_id),
                CONSTRAINT FK_INTEGRATION_SALES_REQUEST_CLIENT FOREIGN KEY (client_id)
                    REFERENCES integration_api_client (id) ON DELETE RESTRICT,
                CONSTRAINT FK_INTEGRATION_SALES_REQUEST_SALE FOREIGN KEY (sale_id)
                    REFERENCES sales (id) ON DELETE RESTRICT
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (bool) $this->connection->fetchOne('SELECT 1 FROM integration_sales_request LIMIT 1'),
            'Cannot discard idempotency history while commercial requests exist.',
        );
        $this->addSql('DROP TABLE integration_sales_request');
        $this->addSql(<<<'SQL'
            ALTER TABLE integration_api_client
                DROP FOREIGN KEY FK_INTEGRATION_API_CLIENT_SALES_BRANCH,
                DROP INDEX IDX_INTEGRATION_API_CLIENT_SALES_BRANCH,
                DROP sales_branch_id
            SQL);
    }
}
