<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns stock_movements.movement_type CHECK with StockMovementType enum values
 * used by StockEntryProcessor (MANUAL_IN, MANUAL_OUT, ADJUSTMENT, etc.).
 */
final class Version20260626130000_stock_movements_type_constraint extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand stock_movements.movement_type CHECK for manual stock entry ledger writes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_movements DROP CONSTRAINT chk_stock_movements_type');
        $this->addSql(<<<'SQL'
ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_type CHECK (movement_type IN (
    'SALE_OUT',
    'RETURN_IN',
    'PURCHASE_IN',
    'TRANSFER_OUT',
    'TRANSFER_IN',
    'ADJUSTMENT',
    'ADJUSTMENT_POS',
    'ADJUSTMENT_NEG',
    'WASTE_OUT',
    'MANUAL_IN',
    'MANUAL_OUT',
    'VOID_REVERSAL',
    'PURCHASE_VOID',
    'RECIPE_CONSUMPTION'
))
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_movements DROP CONSTRAINT chk_stock_movements_type');
        $this->addSql(<<<'SQL'
ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_type CHECK (movement_type IN (
    'SALE_OUT',
    'RETURN_IN',
    'PURCHASE_IN',
    'TRANSFER_OUT',
    'TRANSFER_IN',
    'ADJUSTMENT_POS',
    'ADJUSTMENT_NEG',
    'WASTE_OUT'
))
SQL);
    }
}