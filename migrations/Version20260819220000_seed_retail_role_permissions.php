<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

final class Version20260819220000_seed_retail_role_permissions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed least-privilege retail permissions for administrator and cashier roles';
    }

    public function up(Schema $schema): void
    {
        $permissions = [
            'sales.view', 'sales.create', 'sales.void', 'sales.export', 'sales.collect',
            'pos.access', 'pos.discount', 'pos.price_override', 'pos.offline_sale',
            'cash.view', 'cash.open', 'cash.close', 'cash.close_any', 'cash.movement', 'cash.movement_any', 'cash.manage',
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.cycle_count',
            'catalog.view', 'catalog.create', 'catalog.edit', 'catalog.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.void',
            'reports.view', 'reports.export', 'reports.ple',
            'settings.view', 'settings.edit',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.edit', 'roles.assign',
            'subscriptions.view', 'subscriptions.create', 'subscriptions.edit', 'subscriptions.cancel',
            'accounts_payable.view', 'accounts_payable.create', 'accounts_payable.pay', 'accounts_payable.void',
            'expense_reports.view', 'expense_reports.create', 'expense_reports.approve', 'expense_reports.reimburse',
            'fixed_assets.view', 'fixed_assets.create', 'fixed_assets.dispose',
            'bank_reconciliation.view', 'bank_reconciliation.import', 'bank_reconciliation.match', 'bank_reconciliation.void',
            'sale_quotes.view', 'sale_quotes.create', 'sale_quotes.edit', 'sale_quotes.send', 'sale_quotes.convert', 'sale_quotes.void',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.send', 'purchase_orders.receive', 'purchase_orders.cancel',
            'payroll.view', 'payroll.manage_employees', 'payroll.manage_rates', 'payroll.run', 'payroll.approve', 'payroll.export_plame',
        ];

        foreach ($permissions as $permission) {
            $this->addSql('INSERT IGNORE INTO role_permission (role_id, permission) SELECT 2, ? WHERE EXISTS (SELECT 1 FROM rol WHERE rol_id = 2)', [$permission]);
        }

        $cashierPermissions = [
            'sales.view', 'sales.create', 'sales.collect', 'pos.access', 'pos.offline_sale',
            'cash.view', 'cash.open', 'cash.close', 'inventory.view',
            'catalog.view', 'customers.view', 'customers.create',
        ];
        foreach ($cashierPermissions as $permission) {
            $this->addSql('INSERT IGNORE INTO role_permission (role_id, permission) SELECT 3, ? WHERE EXISTS (SELECT 1 FROM rol WHERE rol_id = 3)', [$permission]);
        }
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('Seeded grants may have been customized after migration.');
    }
}
