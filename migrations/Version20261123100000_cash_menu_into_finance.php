<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The cashier opens, moves and closes the drawer from the point of sale, so "Caja" stops being a
 * sidebar entry of its own: what is left (turn history, registers, categories, team control) is
 * supervision, and it belongs in "Finanzas" next to the money accounts.
 */
final class Version20261123100000_cash_menu_into_finance extends AbstractMigration
{
    private const int FINANCE_GROUP = 790;
    private const int CASH_MENU = 206;

    public function getDescription(): string
    {
        return 'Move the Caja sidebar entry into the Finanzas group as "Cajas y turnos"';
    }

    public function up(Schema $schema): void
    {
        // A tenant whose menu was never seeded with the Finanzas group keeps its Caja entry as it is.
        $this->addSql(
            'UPDATE menu SET order_index = order_index + 1
             WHERE parent_id = ' . self::FINANCE_GROUP . ' AND order_index >= 4
               AND EXISTS (SELECT 1 FROM (SELECT id FROM menu WHERE id = ' . self::CASH_MENU . ') cash)',
        );
        $this->addSql(
            "UPDATE menu SET parent_id = " . self::FINANCE_GROUP . ", order_index = 4, key_name = 'Cajas y turnos'
             WHERE id = " . self::CASH_MENU . '
               AND EXISTS (SELECT 1 FROM (SELECT id FROM menu WHERE id = ' . self::FINANCE_GROUP . ') finance)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE menu SET parent_id = NULL, order_index = 7, key_name = 'Caja' WHERE id = " . self::CASH_MENU,
        );
        $this->addSql(
            'UPDATE menu SET order_index = order_index - 1
             WHERE parent_id = ' . self::FINANCE_GROUP . ' AND order_index > 4',
        );
    }
}
