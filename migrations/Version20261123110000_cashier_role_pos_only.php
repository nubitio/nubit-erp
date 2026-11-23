<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The stock Cajero role becomes what a cashier is: someone who sells. It reads its own sales' receipts
 * (`sales.view_own`) instead of the branch's whole ledger (`sales.view`), it can register the cash that comes
 * in or goes out of its drawer by hand (`cash.movement`: change, a small expense, a withdrawal to the safe),
 * and its menu is the point of sale.
 *
 * Only a role that is still exactly the stock one is touched. A tenant that gave its cashiers more (or
 * less) keeps its choice: its permissions and its menu were decided by someone.
 */
final class Version20261123110000_cashier_role_pos_only extends AbstractMigration
{
    private const int CASHIER_ROLE = 3;
    private const int POS_MENU = 201;

    /** The permissions of the Cajero role as `InitialSetupService` has seeded it until now. */
    private const array STOCK_PERMISSIONS = [
        'cash.close',
        'cash.open',
        'cash.view',
        'catalog.view',
        'customers.create',
        'customers.view',
        'inventory.view',
        'pos.access',
        'pos.offline_sale',
        'sales.collect',
        'sales.create',
        'sales.view',
    ];

    public function getDescription(): string
    {
        return 'Stock Cajero role: sales.view becomes sales.view_own, it may register cash movements and its menu is the point of sale only';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->isStockCashierRole(), 'The Cajero role is not the stock one; its access is left as the tenant set it.');

        $this->addSql('DELETE FROM role_permission WHERE role_id = ' . self::CASHIER_ROLE . " AND permission = 'sales.view'");
        $this->addSql(
            'INSERT IGNORE INTO role_permission (role_id, permission) VALUES (' . self::CASHIER_ROLE . ", 'sales.view_own'), (" . self::CASHIER_ROLE . ", 'cash.movement')",
        );
        $this->addSql('DELETE FROM menu_rol WHERE role_id = ' . self::CASHIER_ROLE . ' AND menu_id <> ' . self::POS_MENU);
        $this->addSql(
            'INSERT IGNORE INTO menu_rol (menu_id, role_id)
             SELECT id, ' . self::CASHIER_ROLE . ' FROM menu WHERE id = ' . self::POS_MENU,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM role_permission WHERE role_id = ' . self::CASHIER_ROLE . " AND permission IN ('sales.view_own', 'cash.movement')",
        );
        $this->addSql(
            'INSERT IGNORE INTO role_permission (role_id, permission) VALUES (' . self::CASHIER_ROLE . ", 'sales.view')",
        );
        $this->addSql(
            'INSERT IGNORE INTO menu_rol (menu_id, role_id) SELECT id, ' . self::CASHIER_ROLE . ' FROM menu',
        );
    }

    private function isStockCashierRole(): bool
    {
        $named = $this->connection->fetchOne(
            'SELECT 1 FROM rol WHERE rol_id = ? AND descripcion = ?',
            [self::CASHIER_ROLE, 'Cajero'],
        );
        if ($named === false) {
            return false;
        }

        /** @var list<string> $granted */
        $granted = $this->connection->fetchFirstColumn(
            'SELECT permission FROM role_permission WHERE role_id = ?',
            [self::CASHIER_ROLE],
        );
        sort($granted);

        return $granted === self::STOCK_PERMISSIONS;
    }
}
