<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes the tenant_inventory_policy table.
 *
 * The per-tenant stock policy is now stored in the generic config table
 * under the key `inventory_negative_stock_policy` (STRICT / WARNING_ONLY /
 * ALLOW_NEGATIVE).  Both TenantStockValidationPolicy (inventory-core v2) and
 * NegativeStockPolicyResolver (legacy sales pipeline) read from that single
 * source, so the dedicated table is no longer needed.
 *
 * Data migration: existing rows are translated to the canonical string values
 * and written into the config table before the table is dropped.
 */
final class Version20260503000003_drop_tenant_inventory_policy extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop tenant_inventory_policy table; migrate policy values to config key inventory_negative_stock_policy';
    }

    public function up(Schema $schema): void
    {
        // 1. For each tenant that has a row in tenant_inventory_policy, upsert
        //    the value into the config table so no data is lost.
        //    The policy column stores StockPolicy enum strings already
        //    (STRICT / WARNING_ONLY / ALLOW_NEGATIVE).
        $this->addSql(<<<'SQL'
            INSERT INTO config (tenant_id, name, value, type)
            SELECT
                tip.tenant_id,
                'inventory_negative_stock_policy',
                tip.policy,
                'string'
            FROM tenant_inventory_policy tip
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        SQL);

        // 2. Drop the now-redundant table.
        $this->addSql('DROP TABLE IF EXISTS tenant_inventory_policy');
    }

    public function down(Schema $schema): void
    {
        // Recreate the table and repopulate from config
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_inventory_policy (
                id         BIGINT AUTO_INCREMENT NOT NULL,
                tenant_id  VARCHAR(128) NOT NULL,
                policy     VARCHAR(20)  NOT NULL DEFAULT 'STRICT',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE INDEX uq_tenant_inventory_policy_tenant (tenant_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO tenant_inventory_policy (tenant_id, policy)
            SELECT tenant_id, value
            FROM config
            WHERE name = 'inventory_negative_stock_policy'
        SQL);
    }
}
