<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000000_nubit_refresh_token extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adopt nubitio/admin-bundle refresh-token storage: create nubit_refresh_token (user identifier string, no FK) and drop the legacy refresh_token table. Active sessions must log in again.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE nubit_refresh_token (
                id INT AUTO_INCREMENT NOT NULL,
                jti VARCHAR(64) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                user_identifier VARCHAR(180) NOT NULL,
                expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_NUBIT_REFRESH_TOKEN_JTI (jti),
                UNIQUE INDEX UNIQ_NUBIT_REFRESH_TOKEN_HASH (token_hash),
                INDEX IDX_NUBIT_REFRESH_TOKEN_USER (user_identifier),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);

        $this->addSql('DROP TABLE IF EXISTS refresh_token');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE nubit_refresh_token');
        // The legacy refresh_token table is not recreated: tokens are
        // short-lived credentials; users re-authenticate after a rollback.
    }
}
