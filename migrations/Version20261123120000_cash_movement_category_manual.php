<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A cashier registers the cash that comes in or goes out of the drawer by hand, and the reasons it can pick are
 * a short list: the ones that describe a person's decision. The rest (a sale, a collection, a refund, a count
 * difference, a payroll or treasury movement) are what the system records itself, so they are not offered.
 */
final class Version20261123120000_cash_movement_category_manual extends AbstractMigration
{
    /** Change from the supervisor, other income, a small expense, a supplier paid at the door, cash taken to the safe. */
    private const array MANUAL_CODES = [
        'cash_replenishment',
        'other_income',
        'petty_cash_expense',
        'supplier_payment',
        'manager_withdrawal',
    ];

    public function getDescription(): string
    {
        return 'Mark which cash movement categories a person may pick (is_manual)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_movement_category ADD is_manual TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql(
            'UPDATE cash_movement_category SET is_manual = 1 WHERE code IN (\''
            . implode("', '", self::MANUAL_CODES) . '\')',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_movement_category DROP is_manual');
    }
}
