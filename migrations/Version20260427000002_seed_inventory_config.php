<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed missing inventory config keys for tenants provisioned before the
 * InitialDataSeeder included them.
 *
 * Idempotent: INSERT ... WHERE NOT EXISTS, so tenants that already have
 * any of these rows are not affected.
 *
 * Keys seeded:
 *   inventory_negative_stock_policy  → ALLOW_NEGATIVE
 *     (was missing → defaulted to BLOCK in code → blocked sales even when
 *      the user disabled app_option_check_stock in the UI)
 *
 *   inventory_core_mode              → legacy
 *   inventory_core_dual_write_enabled        → false
 *   inventory_core_read_stock_items_enabled  → false
 */
final class Version20260427000002_seed_inventory_config extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed missing inventory config keys (negative_stock_policy, core_mode, dual_write, read_stock_items)';
    }

    public function up(Schema $schema): void
    {
        $rows = [
            ['inventory_negative_stock_policy',         'string', 'ALLOW_NEGATIVE'],
            ['inventory_core_mode',                     'string', 'legacy'],
            ['inventory_core_dual_write_enabled',       'bool',   'false'],
            ['inventory_core_read_stock_items_enabled', 'bool',   'false'],
        ];

        foreach ($rows as [$name, $type, $value]) {
            $this->addSql(
                "INSERT INTO config (name, type, value)
                 SELECT :name, :type, :value
                 WHERE NOT EXISTS (SELECT 1 FROM config WHERE name = :name)",
                ['name' => $name, 'type' => $type, 'value' => $value],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM config WHERE name IN (
                'inventory_negative_stock_policy',
                'inventory_core_mode',
                'inventory_core_dual_write_enabled',
                'inventory_core_read_stock_items_enabled'
             )"
        );
    }
}
