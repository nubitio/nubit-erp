<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821130000SeedMiniErpFeatures extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed mini-ERP plan features (payroll, treasury, assets, expenses, recurring billing) on basic and pro.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO plan_features
            (plan_name, feature_key, is_enabled, config_json, created_at, updated_at)
        VALUES
            ('free',  'payroll', 0, NULL, NOW(), NOW()),
            ('basic', 'payroll', 1, NULL, NOW(), NOW()),
            ('pro',   'payroll', 1, NULL, NOW(), NOW()),
            ('free',  'bank_reconciliation', 0, NULL, NOW(), NOW()),
            ('basic', 'bank_reconciliation', 1, NULL, NOW(), NOW()),
            ('pro',   'bank_reconciliation', 1, NULL, NOW(), NOW()),
            ('free',  'fixed_assets', 0, NULL, NOW(), NOW()),
            ('basic', 'fixed_assets', 1, NULL, NOW(), NOW()),
            ('pro',   'fixed_assets', 1, NULL, NOW(), NOW()),
            ('free',  'expense_reports', 0, NULL, NOW(), NOW()),
            ('basic', 'expense_reports', 1, NULL, NOW(), NOW()),
            ('pro',   'expense_reports', 1, NULL, NOW(), NOW()),
            ('free',  'recurring_billing', 0, NULL, NOW(), NOW()),
            ('basic', 'recurring_billing', 1, NULL, NOW(), NOW()),
            ('pro',   'recurring_billing', 1, NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            is_enabled = VALUES(is_enabled),
            updated_at = NOW()");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM plan_features WHERE feature_key IN (
            'payroll', 'bank_reconciliation', 'fixed_assets', 'expense_reports', 'recurring_billing'
        )");
    }
}
