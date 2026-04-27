<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add presentation columns and currency support to the `plans` table.
 *
 * New columns:
 *   - display_name   VARCHAR(100)  NULL
 *   - description    TEXT          NULL
 *   - price_monthly  DECIMAL(10,2) NULL
 *   - price_annual   DECIMAL(10,2) NULL
 *   - currency       VARCHAR(3)    NOT NULL DEFAULT 'PEN'
 *   - is_public      TINYINT(1)    NOT NULL DEFAULT 1
 *   - sort_order     INT           NOT NULL DEFAULT 0
 */
final class Version20260426000001AddPlanPresentationColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add display_name, description, price_monthly, price_annual, currency, is_public, sort_order columns to plans table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE plans
                ADD COLUMN display_name   VARCHAR(100)   NULL         AFTER name,
                ADD COLUMN description    TEXT           NULL         AFTER display_name,
                ADD COLUMN price_monthly  DECIMAL(10,2)  NULL         AFTER description,
                ADD COLUMN price_annual   DECIMAL(10,2)  NULL         AFTER price_monthly,
                ADD COLUMN currency       VARCHAR(3)     NOT NULL DEFAULT 'PEN' AFTER price_annual,
                ADD COLUMN is_public      TINYINT(1)     NOT NULL DEFAULT 1     AFTER currency,
                ADD COLUMN sort_order     INT            NOT NULL DEFAULT 0     AFTER is_public
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE plans
                DROP COLUMN display_name,
                DROP COLUMN description,
                DROP COLUMN price_monthly,
                DROP COLUMN price_annual,
                DROP COLUMN currency,
                DROP COLUMN is_public,
                DROP COLUMN sort_order
        SQL);
    }
}
