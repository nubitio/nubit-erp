<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426000004SeedPlanFeatures extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed plan_features: 42 rows (14 features × 3 planes: free, basic, pro).';
    }

    public function up(Schema $schema): void
    {
        // 14 features × 3 planes = 42 rows
        // Boolean features (11): excel_export, pdf_bulk_download, api_access, multi_warehouse,
        //   lot_tracking, custom_branding, advanced_reports, backup_download,
        //   sunat_auto_retry, webhooks, priority_support
        // Config features (3): api_rate_limit, retention_days, concurrent_sessions
        $this->addSql("INSERT INTO plan_features
            (plan_name, feature_key, is_enabled, config_json, created_at, updated_at)
        VALUES
            -- excel_export
            ('free',  'excel_export', 0, NULL, NOW(), NOW()),
            ('basic', 'excel_export', 1, NULL, NOW(), NOW()),
            ('pro',   'excel_export', 1, NULL, NOW(), NOW()),
            -- pdf_bulk_download
            ('free',  'pdf_bulk_download', 0, NULL, NOW(), NOW()),
            ('basic', 'pdf_bulk_download', 1, NULL, NOW(), NOW()),
            ('pro',   'pdf_bulk_download', 1, NULL, NOW(), NOW()),
            -- api_access
            ('free',  'api_access', 0, NULL, NOW(), NOW()),
            ('basic', 'api_access', 0, NULL, NOW(), NOW()),
            ('pro',   'api_access', 1, NULL, NOW(), NOW()),
            -- multi_warehouse
            ('free',  'multi_warehouse', 0, NULL, NOW(), NOW()),
            ('basic', 'multi_warehouse', 0, NULL, NOW(), NOW()),
            ('pro',   'multi_warehouse', 1, NULL, NOW(), NOW()),
            -- lot_tracking
            ('free',  'lot_tracking', 0, NULL, NOW(), NOW()),
            ('basic', 'lot_tracking', 0, NULL, NOW(), NOW()),
            ('pro',   'lot_tracking', 1, NULL, NOW(), NOW()),
            -- custom_branding
            ('free',  'custom_branding', 0, NULL, NOW(), NOW()),
            ('basic', 'custom_branding', 0, NULL, NOW(), NOW()),
            ('pro',   'custom_branding', 1, NULL, NOW(), NOW()),
            -- advanced_reports
            ('free',  'advanced_reports', 0, NULL, NOW(), NOW()),
            ('basic', 'advanced_reports', 1, NULL, NOW(), NOW()),
            ('pro',   'advanced_reports', 1, NULL, NOW(), NOW()),
            -- backup_download
            ('free',  'backup_download', 0, NULL, NOW(), NOW()),
            ('basic', 'backup_download', 1, NULL, NOW(), NOW()),
            ('pro',   'backup_download', 1, NULL, NOW(), NOW()),
            -- sunat_auto_retry
            ('free',  'sunat_auto_retry', 0, NULL, NOW(), NOW()),
            ('basic', 'sunat_auto_retry', 1, NULL, NOW(), NOW()),
            ('pro',   'sunat_auto_retry', 1, NULL, NOW(), NOW()),
            -- webhooks
            ('free',  'webhooks', 0, NULL, NOW(), NOW()),
            ('basic', 'webhooks', 0, NULL, NOW(), NOW()),
            ('pro',   'webhooks', 1, NULL, NOW(), NOW()),
            -- priority_support
            ('free',  'priority_support', 0, NULL, NOW(), NOW()),
            ('basic', 'priority_support', 0, NULL, NOW(), NOW()),
            ('pro',   'priority_support', 1, NULL, NOW(), NOW()),
            -- api_rate_limit: free=disabled, basic=disabled, pro=enabled+config
            ('free',  'api_rate_limit', 0, NULL,                              NOW(), NOW()),
            ('basic', 'api_rate_limit', 0, NULL,                              NOW(), NOW()),
            ('pro',   'api_rate_limit', 1, '{\"requests_per_hour\": 1000}',   NOW(), NOW()),
            -- retention_days: all enabled, different config
            ('free',  'retention_days', 1, '{\"days\": 90}',  NOW(), NOW()),
            ('basic', 'retention_days', 1, '{\"days\": 365}', NOW(), NOW()),
            ('pro',   'retention_days', 1, '{\"days\": -1}',  NOW(), NOW()),
            -- concurrent_sessions: all enabled, different config
            ('free',  'concurrent_sessions', 1, '{\"max\": 1}',  NOW(), NOW()),
            ('basic', 'concurrent_sessions', 1, '{\"max\": 3}',  NOW(), NOW()),
            ('pro',   'concurrent_sessions', 1, '{\"max\": 10}', NOW(), NOW())
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM plan_features');
    }
}
