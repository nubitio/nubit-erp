<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Cash category for payroll paid from a drawer (ADR-021 WP2), so the arqueo shows it with a name. */
final class Version20261118130000_treasury_payroll_cash_category extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the payroll_payment cash movement category';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO cash_movement_category (code, name, type, is_system, active) VALUES ('payroll_payment', 'Pago de planilla', 'expense', 1, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM cash_movement_category WHERE code = 'payroll_payment' AND id NOT IN (SELECT category_id FROM cash_movements WHERE category_id IS NOT NULL)");
    }
}
