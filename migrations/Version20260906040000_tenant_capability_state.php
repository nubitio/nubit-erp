<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tenant-local operational state for capabilities (NB-5): tenant_capabilities
 * holds per-capability enable/config rows, tenant_profile is the singleton
 * onboarding profile (applied vertical preset + pinned settings fields).
 */
final class Version20260906040000_tenant_capability_state extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_capabilities and tenant_profile tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_capabilities (
                id INT AUTO_INCREMENT NOT NULL,
                capability_key VARCHAR(64) NOT NULL,
                enabled TINYINT(1) NOT NULL,
                config JSON DEFAULT NULL,
                source VARCHAR(20) NOT NULL,
                preset_key VARCHAR(64) DEFAULT NULL,
                preset_version INT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_tenant_capability_key (capability_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_profile (
                id INT AUTO_INCREMENT NOT NULL,
                vertical_key VARCHAR(64) DEFAULT NULL,
                preset_version INT DEFAULT NULL,
                applied_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                locked_fields JSON NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant_capabilities');
        $this->addSql('DROP TABLE tenant_profile');
    }
}
