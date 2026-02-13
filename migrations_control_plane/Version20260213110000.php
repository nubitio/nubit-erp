<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add onboarding control plane tables and extend tenant_provisions for self-service flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS accounts (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                verified_at DATETIME DEFAULT NULL,
                verification_code VARCHAR(6) DEFAULT NULL,
                verification_expires_at DATETIME DEFAULT NULL,
                verification_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_ACCOUNTS_EMAIL (email),
                INDEX IDX_ACCOUNTS_VERIFIED (verified_at),
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS account_tenants (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                account_id INT NOT NULL,
                tenant_id INT NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'owner',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_ACCOUNT_TENANT (account_id, tenant_id),
                INDEX IDX_ACCOUNT_TENANTS_ACCOUNT (account_id),
                INDEX IDX_ACCOUNT_TENANTS_TENANT (tenant_id),
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS reserved_subdomains (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                name VARCHAR(63) NOT NULL,
                UNIQUE INDEX UNIQ_RESERVED_SUBDOMAIN_NAME (name),
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO reserved_subdomains (name) VALUES
            ('admin'), ('api'), ('www'), ('app'), ('mail'), ('staging'), ('dev'), ('stg'),
            ('test'), ('demo'), ('support'), ('help'), ('billing'), ('status'), ('docs')
        SQL);

        $this->addSql('ALTER TABLE tenant_provisions MODIFY tenant_id INT UNSIGNED NULL');
        $this->addSql('ALTER TABLE tenant_provisions ADD COLUMN IF NOT EXISTS account_id INT NULL AFTER tenant_id');
        $this->addSql("ALTER TABLE tenant_provisions ADD COLUMN IF NOT EXISTS plan VARCHAR(50) NOT NULL DEFAULT 'free' AFTER domain");
        $this->addSql('ALTER TABLE tenant_provisions ADD COLUMN IF NOT EXISTS public_token CHAR(64) NULL AFTER status');
        $this->addSql('ALTER TABLE tenant_provisions ADD INDEX IF NOT EXISTS IDX_PROV_ACCOUNT (account_id)');
        $this->addSql('ALTER TABLE tenant_provisions ADD UNIQUE INDEX IF NOT EXISTS UNIQ_PROV_PUBLIC_TOKEN (public_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_provisions DROP FOREIGN KEY IF EXISTS FK_PROV_ACCOUNT');
        $this->addSql('ALTER TABLE tenant_provisions DROP INDEX IF EXISTS UNIQ_PROV_PUBLIC_TOKEN');
        $this->addSql('ALTER TABLE tenant_provisions DROP INDEX IF EXISTS IDX_PROV_ACCOUNT');
        $this->addSql('ALTER TABLE tenant_provisions DROP COLUMN IF EXISTS public_token');
        $this->addSql('ALTER TABLE tenant_provisions DROP COLUMN IF EXISTS plan');
        $this->addSql('ALTER TABLE tenant_provisions DROP COLUMN IF EXISTS account_id');
        $this->addSql('ALTER TABLE tenant_provisions MODIFY tenant_id INT UNSIGNED NOT NULL');

        $this->addSql('DROP TABLE IF EXISTS account_tenants');
        $this->addSql('DROP TABLE IF EXISTS reserved_subdomains');
        $this->addSql('DROP TABLE IF EXISTS accounts');
    }
}
