<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-7: seed the four capability config values that used to live only on
 * tenant_settings (requireCashRegister, autoInvoice, negativeStockPolicy,
 * printMethod). Each tenant DB has a single tenant_settings row. Existing
 * capability rows keep their enabled/source and only gain the mapped config key.
 */
final class Version20260906050000_backfill_capability_config_from_settings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill cash.sessions / billing.sunat / inventory.stock_tracking / hardware.printing config from tenant_settings';
    }

    public function up(Schema $schema): void
    {
        $this->seed(
            'cash.sessions',
            "JSON_OBJECT('requireOpenSession', s.require_cash_register = 1)",
            "'$.requireOpenSession', (SELECT require_cash_register = 1 FROM tenant_settings LIMIT 1)",
        );
        $this->seed(
            'billing.sunat',
            "JSON_OBJECT('autoInvoice', s.auto_invoice = 1)",
            "'$.autoInvoice', (SELECT auto_invoice = 1 FROM tenant_settings LIMIT 1)",
        );
        $this->seed(
            'inventory.stock_tracking',
            "JSON_OBJECT('negativeStockPolicy', s.negative_stock_policy)",
            "'$.negativeStockPolicy', (SELECT negative_stock_policy FROM tenant_settings LIMIT 1)",
        );
        $this->seed(
            'hardware.printing',
            "JSON_OBJECT('method', s.print_method)",
            "'$.method', (SELECT print_method FROM tenant_settings LIMIT 1)",
        );
    }

    public function down(Schema $schema): void
    {
        // Values remain mirrored on tenant_settings; nothing to roll back.
    }

    private function seed(string $key, string $insertConfig, string $jsonSetArgs): void
    {
        $this->addSql(<<<SQL
            INSERT INTO tenant_capabilities (capability_key, enabled, config, source, created_at, updated_at)
            SELECT '{$key}', 1, {$insertConfig}, 'migration', NOW(), NOW()
            FROM tenant_settings s
            ON DUPLICATE KEY UPDATE config = JSON_SET(COALESCE(config, JSON_OBJECT()), {$jsonSetArgs})
            SQL);
    }
}
