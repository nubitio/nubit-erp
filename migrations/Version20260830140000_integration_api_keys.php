<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Integration API clients + keys for the tenant-scoped `/api/v1` surface.
 */
final class Version20260830140000_integration_api_keys extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create integration_api_client and integration_api_key tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_api_client (
                id VARCHAR(255) NOT NULL,
                name VARCHAR(120) NOT NULL,
                scopes JSON NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE integration_api_key (
                id VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                prefix VARCHAR(40) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                last_used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id),
                UNIQUE INDEX uq_integration_api_key_prefix (prefix),
                INDEX IDX_integration_api_key_client (client_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE integration_api_key
                ADD CONSTRAINT FK_integration_api_key_client
                FOREIGN KEY (client_id) REFERENCES integration_api_client (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_api_key DROP FOREIGN KEY FK_integration_api_key_client');
        $this->addSql('DROP TABLE integration_api_key');
        $this->addSql('DROP TABLE integration_api_client');
    }
}
