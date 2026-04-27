<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove the redundant app_option_check_stock config key.
 *
 * The frontend used to read this key to decide whether to show a stock
 * warning in the sales form and POS. The real stock enforcement was always
 * controlled by inventory_negative_stock_policy (backend). Having two
 * separate settings was confusing: disabling app_option_check_stock did not
 * prevent the backend from blocking sales, causing silent UX failures.
 *
 * Both the frontend and the admin config page now use
 * inventory_negative_stock_policy exclusively:
 *   ALLOW_NEGATIVE  → no UI check, backend allows negative stock
 *   BLOCK / WARNING_ONLY → UI warns, backend enforces or role-gates
 */
final class Version20260427000003_drop_check_stock_config extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove redundant app_option_check_stock config key (replaced by inventory_negative_stock_policy)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name = 'app_option_check_stock'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO config (name, type, value)
             SELECT 'app_option_check_stock', 'bool',
                 CASE WHEN (SELECT value FROM config WHERE name = 'inventory_negative_stock_policy') = 'ALLOW_NEGATIVE'
                      THEN 'false' ELSE 'true' END
             WHERE NOT EXISTS (SELECT 1 FROM config WHERE name = 'app_option_check_stock')"
        );
    }
}
