<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126100000_backfill_operational_branches extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recover historical operational branch from warehouse and document references when they agree';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE sales s
            JOIN almacen w ON w.almacen_id = s.warehouse_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN cash_register r ON r.id = s.cash_register_id
            SET s.branch_id = w.local_id
            WHERE s.branch_id IS NULL AND w.local_id IS NOT NULL
              AND (s.cash_register_id IS NULL OR r.branch_id = w.local_id COLLATE utf8mb4_unicode_ci)
            SQL);

        foreach (['sale_quotes', 'purchase_orders'] as $table) {
            $this->addSql(<<<SQL
                UPDATE {$table} d
                JOIN almacen w ON w.almacen_id = d.warehouse_id COLLATE utf8mb4_unicode_ci
                SET d.branch_id = w.local_id
                WHERE d.branch_id IS NULL AND w.local_id IS NOT NULL
                SQL);
        }

        $this->addSql(<<<'SQL'
            UPDATE goods_receipts r
            JOIN almacen w ON w.almacen_id = r.warehouse_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN purchase_orders p ON p.id = r.purchase_order_id COLLATE utf8mb4_unicode_ci
            SET r.branch_id = w.local_id
            WHERE r.branch_id IS NULL AND w.local_id IS NOT NULL
              AND (r.purchase_order_id IS NULL OR p.branch_id = w.local_id COLLATE utf8mb4_unicode_ci)
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE despatch_guide d
            JOIN sales s ON s.id = d.sale_id COLLATE utf8mb4_unicode_ci
            SET d.branch_id = s.branch_id
            WHERE d.branch_id IS NULL AND s.branch_id IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Backfilled branches cannot be distinguished from manually assigned branches.');
    }
}
