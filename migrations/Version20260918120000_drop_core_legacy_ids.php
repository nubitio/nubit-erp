<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sales/purchases V2 and cash_sessions are authoritative. These columns were
 * leftover join keys after `movimiento` / `cash_shift` were dropped.
 */
final class Version20260918120000_drop_core_legacy_ids extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop leftover sales/purchases/cash legacy movimiento and cash_shift id columns';
    }

    public function up(Schema $schema): void
    {
        $this->dropIndexAndColumn('sales', 'uq_sales_legacy_movimiento', 'legacy_movimiento_id');
        $this->dropIndexAndColumn('sale_items', 'uq_sale_items_legacy_detail', 'legacy_movimiento_detalle_id');
        $this->dropIndexAndColumn('goods_receipts', 'uq_goods_receipts_legacy_movimiento', 'legacy_movimiento_id');
        $this->dropIndexAndColumn(
            'goods_receipt_items',
            'uq_goods_receipt_items_legacy_detail',
            'legacy_movimiento_detalle_id',
        );
        $this->dropIndexAndColumn('cash_sessions', 'uq_cash_sessions_legacy_cash_shift', 'legacy_cash_shift_id');
    }

    public function down(Schema $schema): void
    {
        $this->addColumnAndUniqueIndex('sales', 'legacy_movimiento_id', 'VARCHAR(255) DEFAULT NULL', 'uq_sales_legacy_movimiento');
        $this->addColumnAndUniqueIndex('sale_items', 'legacy_movimiento_detalle_id', 'INT DEFAULT NULL', 'uq_sale_items_legacy_detail');
        $this->addColumnAndUniqueIndex('goods_receipts', 'legacy_movimiento_id', 'VARCHAR(255) DEFAULT NULL', 'uq_goods_receipts_legacy_movimiento');
        $this->addColumnAndUniqueIndex(
            'goods_receipt_items',
            'legacy_movimiento_detalle_id',
            'INT DEFAULT NULL',
            'uq_goods_receipt_items_legacy_detail',
        );
        $this->addColumnAndUniqueIndex('cash_sessions', 'legacy_cash_shift_id', 'INT DEFAULT NULL', 'uq_cash_sessions_legacy_cash_shift');
    }

    private function dropIndexAndColumn(string $table, string $index, string $column): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s DROP INDEX %s', $table, $index));
        $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column));
    }

    private function addColumnAndUniqueIndex(string $table, string $column, string $definition, string $index): void
    {
        if (!$this->tableExists($table) || $this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
        $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON %s (%s)', $index, $table, $column));
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table],
        ) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $table, 'column' => $column],
        ) > 0;
    }
}
