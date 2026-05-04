<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Camino A — Config refactor (Part 1/2):
 *
 * 1. Creates tenant_settings (typed user-facing settings).
 * 2. Creates sunat_operational_state (SUNAT operational flags + streak).
 * 3. Creates exchange_rates (replaces config rows for exchange rate).
 * 4. Populates all three tables from existing config rows.
 * 5. Deletes all migration flags (SALES_V2_*, PURCHASES_V2_*, INVENTORY_CORE_*,
 *    SUNAT retry params) from the config table — they are no longer needed.
 *
 * The original config table is NOT dropped here to allow backward compatibility
 * during the frontend/Android transition period (deprecated /api/configs endpoint
 * still reads from it). It will be dropped in Part 2 after the transition window.
 */
final class Version20260504000001_config_refactor_part1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config refactor Part 1: create tenant_settings, sunat_operational_state, exchange_rates; migrate data; remove migration flags';
    }

    public function up(Schema $schema): void
    {
        // Drop legacy key/value tenant_settings if it exists (created by Version20260504000000).
        // The new schema uses typed columns instead of name/value/type rows.
        $this->addSql('DROP TABLE IF EXISTS tenant_settings');

        // ── 1. tenant_settings ────────────────────────────────────────────────
        $this->addSql(
            <<<'SQL'
            CREATE TABLE tenant_settings (
                id                      INT AUTO_INCREMENT NOT NULL,
                igv                     DOUBLE PRECISION NOT NULL DEFAULT 18,
                rc                      DOUBLE PRECISION NOT NULL DEFAULT 0,
                print_logo              TINYINT(1) NOT NULL DEFAULT 1,
                require_cash_register   TINYINT(1) NOT NULL DEFAULT 1,
                print_method            VARCHAR(20) NOT NULL DEFAULT 'WEBUSB',
                auto_invoice            TINYINT(1) NOT NULL DEFAULT 1,
                default_currency        VARCHAR(3) NOT NULL DEFAULT 'PEN',
                negative_stock_policy   VARCHAR(20) NOT NULL DEFAULT 'STRICT',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        // Seed from existing config rows (coalesce to sane defaults if missing).
        $this->addSql(
            <<<'SQL'
            INSERT INTO tenant_settings
                (igv, rc, print_logo, require_cash_register, print_method,
                 auto_invoice, default_currency, negative_stock_policy)
            SELECT
                COALESCE((SELECT CAST(value AS DECIMAL(5,2)) FROM config WHERE name = 'app_option_igv' LIMIT 1), 18),
                COALESCE((SELECT CAST(value AS DECIMAL(5,2)) FROM config WHERE name = 'app_option_rc' LIMIT 1), 0),
                COALESCE((SELECT value = 'true' FROM config WHERE name = 'app_option_print_logo' LIMIT 1), 1),
                COALESCE((SELECT value = 'true' FROM config WHERE name = 'app_option_require_cash_register' LIMIT 1), 1),
                COALESCE((SELECT value FROM config WHERE name = 'print_method' LIMIT 1), 'WEBUSB'),
                COALESCE((SELECT value = 'true' FROM config WHERE name = 'app_option_auto_invoice' LIMIT 1), 1),
                COALESCE((SELECT value FROM config WHERE name = 'sales_default_currency' LIMIT 1), 'PEN'),
                COALESCE(
                    (SELECT CASE value
                        WHEN 'BLOCK' THEN 'STRICT'
                        WHEN 'ALLOW_WITH_ROLE' THEN 'WARNING_ONLY'
                        WHEN 'ALLOW_ALL' THEN 'ALLOW_NEGATIVE'
                        ELSE value
                    END FROM config WHERE name = 'inventory_negative_stock_policy' LIMIT 1),
                    'STRICT'
                )
            SQL
        );

        // ── 2. sunat_operational_state ────────────────────────────────────────
        $this->addSql(
            <<<'SQL'
            CREATE TABLE sunat_operational_state (
                id                  INT AUTO_INCREMENT NOT NULL,
                send_enabled        TINYINT(1) NOT NULL DEFAULT 1,
                receipt_required    TINYINT(1) NOT NULL DEFAULT 0,
                rejection_streak    INT NOT NULL DEFAULT 0,
                rejection_threshold INT NOT NULL DEFAULT 3,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            INSERT INTO sunat_operational_state
                (send_enabled, receipt_required, rejection_streak, rejection_threshold)
            SELECT
                COALESCE((SELECT value = 'true' FROM config WHERE name = 'app_option_sunat_send_enabled' LIMIT 1), 1),
                COALESCE((SELECT value = 'true' FROM config WHERE name = 'app_option_receipt_required' LIMIT 1), 0),
                COALESCE((SELECT CAST(value AS UNSIGNED) FROM config WHERE name = 'app_option_sunat_rejection_streak' LIMIT 1), 0),
                COALESCE((SELECT CAST(value AS UNSIGNED) FROM config WHERE name = 'app_option_sunat_rejection_threshold' LIMIT 1), 3)
            SQL
        );

        // ── 3. exchange_rates ─────────────────────────────────────────────────
        $this->addSql(
            <<<'SQL'
            CREATE TABLE exchange_rates (
                id          INT AUTO_INCREMENT NOT NULL,
                currency    VARCHAR(3) NOT NULL,
                rate        NUMERIC(12, 6) NOT NULL,
                source      VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
                updated_at  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uq_currency (currency),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
            INSERT INTO exchange_rates (currency, rate, source, updated_at)
            SELECT
                'USD',
                COALESCE(
                    (SELECT CAST(value AS DECIMAL(12,6)) FROM config WHERE name = 'sales_default_usd_exchange_rate' LIMIT 1),
                    3.800000
                ),
                'BCRP_AUTO',
                COALESCE(
                    (SELECT STR_TO_DATE(value, '%Y-%m-%d %H:%i:%s') FROM config WHERE name = 'sales_usd_exchange_rate_updated_at' LIMIT 1),
                    NOW()
                )
            SQL
        );

        // ── 4. Remove migration flags + retired config rows ───────────────────
        $keysToDelete = [
            // Migration flags — all tenants are on v2_authoritative
            'sales_v2_mode',
            'app_option_sales_v2_dual_write_enabled',
            'app_option_sales_v2_dual_write_strict',
            'sales_v2_cutover_date',
            'sales_v2_disable_legacy_cash_movement',
            'sales_v2_disable_legacy_stock_projection',
            'purchases_v2_mode',
            'app_option_purchases_v2_dual_write_enabled',
            'app_option_purchases_v2_dual_write_strict',
            'inventory_core_mode',
            'inventory_core_dual_write_enabled',
            'inventory_core_read_stock_items_enabled',
            // SUNAT retry params moved to sunat.yaml
            'app_option_sunat_send_retry_max_attempts',
            'app_option_sunat_send_retry_base_seconds',
            'app_option_sunat_send_retry_max_seconds',
            // Exchange rate moved to exchange_rates table
            'sales_default_usd_exchange_rate',
            'sales_usd_exchange_rate_updated_at',
        ];

        $placeholders = implode(',', array_fill(0, count($keysToDelete), '?'));
        $this->addSql(
            "DELETE FROM config WHERE name IN ({$placeholders})",
            $keysToDelete
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenant_settings');
        $this->addSql('DROP TABLE IF EXISTS sunat_operational_state');
        $this->addSql('DROP TABLE IF EXISTS exchange_rates');
        // Note: restoring deleted config rows on down() is intentionally omitted
        // as it would require re-deriving values that no longer exist.
    }
}
