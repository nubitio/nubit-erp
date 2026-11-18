<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Cash categories for money moved between a drawer and a bank or wallet, so the arqueo shows it with a name. */
final class Version20261119090000_treasury_transfer_cash_categories extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the cash movement categories for moving money between a drawer and a bank or wallet';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT IGNORE INTO cash_movement_category (code, name, type, is_system, active) VALUES ('treasury_transfer_out', 'Transferencia a otra cuenta', 'expense', 1, 1)");
        $this->addSql("INSERT IGNORE INTO cash_movement_category (code, name, type, is_system, active) VALUES ('treasury_transfer_in', 'Transferencia desde otra cuenta', 'income', 1, 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM cash_movement_category WHERE code IN ('treasury_transfer_out', 'treasury_transfer_in') AND id NOT IN (SELECT category_id FROM cash_movements WHERE category_id IS NOT NULL)");
    }
}
