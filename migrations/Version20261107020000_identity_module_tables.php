<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tables of nubit/admin-bundle's identity module (TOTP second factor, invitation and
 * password-reset tokens, API keys). Purely additive: nothing reads them until
 * IDENTITY_MODULE_ENABLED=1 (see config/packages/nubit_admin_identity.php).
 *
 * They MUST exist in every tenant database BEFORE the module is switched on, because the login
 * then asks nubit_totp_credential whether the user has a second factor.
 */
final class Version20261107020000_identity_module_tables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create nubit_totp_credential, nubit_identity_token and nubit_api_key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE nubit_totp_credential (
                id INT AUTO_INCREMENT NOT NULL,
                user_identifier VARCHAR(180) NOT NULL,
                secret VARCHAR(64) NOT NULL,
                confirmed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                last_used_step INT DEFAULT NULL,
                recovery_codes JSON NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_NUBIT_TOTP_USER (user_identifier),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE nubit_identity_token (
                id INT AUTO_INCREMENT NOT NULL,
                purpose VARCHAR(32) NOT NULL,
                subject VARCHAR(180) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                consumed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                roles JSON NOT NULL,
                created_by VARCHAR(180) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_NUBIT_IDENTITY_TOKEN_HASH (token_hash),
                INDEX IDX_NUBIT_IDENTITY_TOKEN_SUBJECT (purpose, subject),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE nubit_api_key (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(120) NOT NULL,
                prefix VARCHAR(16) NOT NULL,
                key_hash VARCHAR(64) NOT NULL,
                user_identifier VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                last_used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_by VARCHAR(180) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_NUBIT_API_KEY_HASH (key_hash),
                INDEX IDX_NUBIT_API_KEY_PREFIX (prefix),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE nubit_api_key');
        $this->addSql('DROP TABLE nubit_identity_token');
        $this->addSql('DROP TABLE nubit_totp_credential');
    }
}
