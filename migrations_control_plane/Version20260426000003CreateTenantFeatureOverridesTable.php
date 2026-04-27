<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the `tenant_feature_overrides` table for per-tenant feature flag overrides.
 *
 * - FK to `tenants.id` with ON DELETE CASCADE
 * - `expires_at` nullable (TTL-based overrides)
 * - `cleaned_at` nullable (soft-cleanup marker for cron, avoids hard DELETE)
 * - Unique constraint on (tenant_id, feature_key)
 */
final class Version20260426000003CreateTenantFeatureOverridesTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_feature_overrides table with FK to tenants and nullable expires_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_feature_overrides (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id   INT          NOT NULL,
                feature_key VARCHAR(64)  NOT NULL,
                is_enabled  TINYINT(1)   NOT NULL,
                config_json JSON         NULL,
                expires_at  DATETIME     NULL,
                reason      VARCHAR(255) NULL,
                cleaned_at  DATETIME     NULL,
                created_at  DATETIME     NOT NULL,
                UNIQUE KEY uq_tenant_feature (tenant_id, feature_key),
                INDEX idx_tfo_expires (expires_at),
                INDEX idx_tfo_tenant (tenant_id),
                CONSTRAINT fk_tfo_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenant_feature_overrides');
    }
}
