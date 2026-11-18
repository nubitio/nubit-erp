<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cash categories for supplier payments made from a drawer and their reversal (ADR-021 WP2). Without them the
 * arqueo would show these movements with no category. System categories, never removed by the app.
 */
final class Version20261118120000_treasury_cash_categories extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the supplier_payment and supplier_payment_reversal cash movement categories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO cash_movement_category (code, name, type, is_system, active) VALUES ('supplier_payment', 'Pago a proveedor', 'expense', 1, 1)");
        $this->addSql("INSERT IGNORE INTO cash_movement_category (code, name, type, is_system, active) VALUES ('supplier_payment_reversal', 'Reversión de pago a proveedor', 'income', 1, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM cash_movement_category WHERE code IN ('supplier_payment', 'supplier_payment_reversal') AND id NOT IN (SELECT category_id FROM cash_movements WHERE category_id IS NOT NULL)");
    }
}
