<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Service\SettingsService;
use App\Core\Exception\ServiceException;
use App\Core\Util\Constants;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Warehouse;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Inventory\Core\Service\LotDeductionService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

/**
 * Ledger-first inventory service.
 *
 * Source of truth: stock_movements
 * Read model: stock_balances
 * Legacy compatibility reads: product_stock fallback for old tenants
 */
final readonly class InventoryLedgerService
{
    public function __construct(
        private Connection $connection,
        private ?SettingsService $settingsService = null,
        private ?LotDeductionService $lotDeductionService = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float
    {
        $warehouseId = (string) $warehouse->getId();
        $productId = (string) $product->getId();

        if ($this->shouldReadFromStockItems()) {
            $fromStockItems = $this->connection->fetchOne(
                <<<'SQL'
SELECT qty_on_hand
FROM stock_items
WHERE warehouse_id = :warehouse_id
  AND product_id = :product_id
LIMIT 1
SQL,
                [
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                ]
            );

            if ($fromStockItems !== false) {
                return (float) $fromStockItems;
            }
        }

        $fromBalances = $this->connection->fetchOne(
            <<<'SQL'
SELECT available_qty
FROM stock_balances
WHERE warehouse_id = :warehouse_id
  AND product_id = :product_id
  AND variant_id = ''
LIMIT 1
SQL,
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        if ($fromBalances !== false) {
            return (float) $fromBalances;
        }

        $fromLegacy = $this->connection->fetchOne(
            <<<'SQL'
SELECT stock
FROM product_stock
WHERE warehouse_id = :warehouse_id
  AND product_id = :product_id
LIMIT 1
SQL,
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        return $fromLegacy !== false ? (float) $fromLegacy : 0.0;
    }

    /**
     * @throws Exception
     * @throws ServiceException
     */
    public function applyMovementFromSaleItem(
        Sale $sale,
        SaleItem $item,
        int $lineNo,
        bool $enforceNonNegativeStock = true,
    ): void {
        $warehouse = $sale->getAlmacen();
        $product = $item->getProducto();
        if ($warehouse === null || $product === null) {
            return;
        }
        if (!$this->shouldTrackInventory($product)) {
            return;
        }

        $movementType = $sale->getTipoId() === 1 ? 'PURCHASE_IN' : 'SALE_OUT';
        $qty = (float) $item->getCantidad();
        $delta = $sale->getTipoId() === 1 ? $qty : -$qty;
        $unitCost = $this->resolveUnitCost($sale, $item);

        $saleId = (string) ($sale->getId() ?? Uuid::v7()->toRfc4122());
        $sourceType = 'LEGACY_MOVIMIENTO_ITEM';
        $sourceId = sprintf('%s:%s:%d', $saleId, $movementType, $lineNo);
        $idempotencyKey = $this->deterministicUuid($sourceType . ':' . $sourceId);

        $lotDeductions = $this->resolveLotDeductions($product, $warehouse, $movementType, $qty);

        $this->connection->beginTransaction();
        try {
            try {
                if ($lotDeductions !== []) {
                    foreach ($lotDeductions as $i => ['lot' => $lot, 'qty' => $lotQty]) {
                        $lotDelta = $delta < 0 ? -$lotQty : $lotQty;
                        $this->insertStockMovement(
                            sale: $sale,
                            item: $item,
                            movementType: $movementType,
                            delta: $lotDelta,
                            unitCost: $unitCost,
                            sourceType: $sourceType,
                            sourceId: $sourceId . ':lot' . $i,
                            idempotencyKey: $this->deterministicUuid($idempotencyKey . ':lot' . $i),
                            lotId: (string) $lot->getId(),
                        );
                    }
                } else {
                    $this->insertStockMovement(
                        sale: $sale,
                        item: $item,
                        movementType: $movementType,
                        delta: $delta,
                        unitCost: $unitCost,
                        sourceType: $sourceType,
                        sourceId: $sourceId,
                        idempotencyKey: $idempotencyKey,
                    );
                }
            } catch (UniqueConstraintViolationException) {
                // Idempotent replay: movement already applied.
                $this->connection->rollBack();

                return;
            }

            $this->applyStockBalanceDelta(
                warehouseId: (string) $warehouse->getId(),
                productId: (string) $product->getId(),
                delta: $delta,
                unitCost: $unitCost,
                occurredAt: $sale->getFecha() ?? new \DateTimeImmutable(),
                enforceNonNegativeStock: $enforceNonNegativeStock
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    /**
     * Resolve lot deductions for FIFO/FEFO strategies.
     * Returns empty array when lot tracking is disabled or column doesn't exist.
     *
     * @return array<array{lot: \App\Entity\StockLot, qty: float}>
     */
    private function resolveLotDeductions(
        Product $product,
        \App\Entity\Warehouse $warehouse,
        string $movementType,
        float $qty,
    ): array {
        if ($movementType !== 'SALE_OUT') {
            return [];
        }

        $strategy = $product->getLotTrackingStrategyEnum();
        if ($strategy === LotTrackingStrategy::NONE) {
            return [];
        }

        if ($this->lotDeductionService === null) {
            return [];
        }

        if (!$this->hasColumn('stock_movements', 'lot_id')) {
            return [];
        }

        return $this->lotDeductionService->deduct(
            productId: (string) $product->getId(),
            warehouseId: (string) $warehouse->getId(),
            qty: $qty,
            strategy: $strategy,
        );
    }

    /**
     * @throws Exception
     */
    private function insertStockMovement(
        Sale $sale,
        SaleItem $item,
        string $movementType,
        float $delta,
        ?float $unitCost,
        string $sourceType,
        string $sourceId,
        string $idempotencyKey,
        ?string $lotId = null,
    ): void {
        $warehouseId = (string) $sale->getAlmacen()?->getId();
        $productId = (string) $item->getProducto()?->getId();
        $occurredAt = ($sale->getFecha() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $totalCost = $unitCost !== null ? round(abs($delta) * $unitCost, 2) : null;
        $supportsLotId = $lotId !== null && $this->hasColumn('stock_movements', 'lot_id');
        $params = [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'occurred_at' => $occurredAt,
            'movement_type' => $movementType,
            'quantity_delta' => number_format($delta, 3, '.', ''),
            'unit_cost' => $unitCost !== null ? number_format($unitCost, 4, '.', '') : null,
            'total_cost' => $totalCost !== null ? number_format($totalCost, 2, '.', '') : null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_code' => (string) ($sale->getId() ?? ''),
            'note' => 'Applied by InventoryLedgerService',
            'idempotency_key' => $idempotencyKey,
        ];

        $supportsBaseQtyColumns = $this->hasColumn('stock_movements', 'qty_delta_base')
            && $this->hasColumn('stock_movements', 'qty_original');

        if ($supportsBaseQtyColumns) {
            $params['qty_delta_base'] = number_format($delta, 6, '.', '');
            $params['qty_original'] = number_format(abs($delta), 6, '.', '');
            $params['unit_id'] = $this->hasColumn('stock_movements', 'unit_id')
                ? $this->resolveMovementUnitId($productId)
                : null;

            $lotColumn = $supportsLotId ? "\n    lot_id," : '';
            $lotValue = $supportsLotId ? "\n    :lot_id," : '';
            if ($supportsLotId) {
                $params['lot_id'] = $lotId;
            }

            $this->connection->executeStatement(
                <<<SQL
INSERT INTO stock_movements (
    warehouse_id,
    product_id,
    variant_id,{$lotColumn}
    occurred_at,
    movement_type,
    quantity_delta,
    qty_delta_base,
    unit_id,
    qty_original,
    unit_cost,
    total_cost,
    source_type,
    source_id,
    reference_code,
    note,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at,
    created_at
) VALUES (
    :warehouse_id,
    :product_id,
    NULL,{$lotValue}
    :occurred_at,
    :movement_type,
    :quantity_delta,
    :qty_delta_base,
    :unit_id,
    :qty_original,
    :unit_cost,
    :total_cost,
    :source_type,
    :source_id,
    :reference_code,
    :note,
    :idempotency_key,
    'SYNCED',
    NOW(),
    NULL,
    1,
    NOW(),
    NOW()
)
SQL,
                $params
            );

            return;
        }

        $lotColumn = $supportsLotId ? "\n    lot_id," : '';
        $lotValue = $supportsLotId ? "\n    :lot_id," : '';
        if ($supportsLotId) {
            $params['lot_id'] = $lotId;
        }

        $this->connection->executeStatement(
            <<<SQL
INSERT INTO stock_movements (
    warehouse_id,
    product_id,
    variant_id,{$lotColumn}
    occurred_at,
    movement_type,
    quantity_delta,
    unit_cost,
    total_cost,
    source_type,
    source_id,
    reference_code,
    note,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at,
    created_at
) VALUES (
    :warehouse_id,
    :product_id,
    NULL,{$lotValue}
    :occurred_at,
    :movement_type,
    :quantity_delta,
    :unit_cost,
    :total_cost,
    :source_type,
    :source_id,
    :reference_code,
    :note,
    :idempotency_key,
    'SYNCED',
    NOW(),
    NULL,
    1,
    NOW(),
    NOW()
)
SQL,
            $params
        );
    }

    /**
     * @throws Exception
     * @throws ServiceException
     */
    private function applyStockBalanceDelta(
        string $warehouseId,
        string $productId,
        float $delta,
        ?float $unitCost,
        \DateTimeImmutable $occurredAt,
        bool $enforceNonNegativeStock,
    ): void {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT on_hand_qty, reserved_qty, available_qty, avg_cost
FROM stock_balances
WHERE warehouse_id = :warehouse_id
  AND product_id = :product_id
  AND variant_id = ''
FOR UPDATE
SQL,
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        if ($row === false) {
            $currentOnHand = 0.0;
            $currentReserved = 0.0;
            $currentAvailable = 0.0;
            $currentAvgCost = null;
        } else {
            $currentOnHand = (float) ($row['on_hand_qty'] ?? 0.0);
            $currentReserved = (float) ($row['reserved_qty'] ?? 0.0);
            $currentAvailable = (float) ($row['available_qty'] ?? 0.0);
            $currentAvgCost = $row['avg_cost'] !== null ? (float) $row['avg_cost'] : null;
        }

        $newOnHand = round($currentOnHand + $delta, 3);
        $newAvailable = round($currentAvailable + $delta, 3);
        if ($enforceNonNegativeStock && $newOnHand < -0.0005) {
            throw new ServiceException(sprintf(
                'Stock insuficiente para producto %s en almacén %s.',
                $productId,
                $warehouseId
            ));
        }

        $newAvgCost = $currentAvgCost;
        if ($delta > 0 && $unitCost !== null) {
            $weightedBaseQty = max(0.0, $currentOnHand);
            $weightedBaseCost = $weightedBaseQty * (float) ($currentAvgCost ?? 0.0);
            $incomingCost = $delta * $unitCost;
            $qtyForAverage = $weightedBaseQty + $delta;
            $newAvgCost = $qtyForAverage > 0 ? round(($weightedBaseCost + $incomingCost) / $qtyForAverage, 4) : null;
        }

        if ($row === false) {
            $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO stock_balances (
    warehouse_id,
    product_id,
    variant_id,
    on_hand_qty,
    reserved_qty,
    available_qty,
    avg_cost,
    last_movement_at,
    updated_at
) VALUES (
    :warehouse_id,
    :product_id,
    '',
    :on_hand_qty,
    :reserved_qty,
    :available_qty,
    :avg_cost,
    :last_movement_at,
    NOW()
)
SQL,
                [
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'on_hand_qty' => number_format($newOnHand, 3, '.', ''),
                    'reserved_qty' => number_format($currentReserved, 3, '.', ''),
                    'available_qty' => number_format($newAvailable, 3, '.', ''),
                    'avg_cost' => $newAvgCost !== null ? number_format($newAvgCost, 4, '.', '') : null,
                    'last_movement_at' => $occurredAt->format('Y-m-d H:i:s'),
                ]
            );

            $this->syncStockItem($warehouseId, $productId, $newOnHand, $currentReserved);

            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
UPDATE stock_balances
SET
    on_hand_qty = :on_hand_qty,
    reserved_qty = :reserved_qty,
    available_qty = :available_qty,
    avg_cost = :avg_cost,
    last_movement_at = :last_movement_at,
    updated_at = NOW()
WHERE warehouse_id = :warehouse_id
  AND product_id = :product_id
  AND variant_id = ''
SQL,
            [
                'on_hand_qty' => number_format($newOnHand, 3, '.', ''),
                'reserved_qty' => number_format($currentReserved, 3, '.', ''),
                'available_qty' => number_format($newAvailable, 3, '.', ''),
                'avg_cost' => $newAvgCost !== null ? number_format($newAvgCost, 4, '.', '') : null,
                'last_movement_at' => $occurredAt->format('Y-m-d H:i:s'),
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ]
        );

        $this->syncStockItem($warehouseId, $productId, $newOnHand, $currentReserved);
    }

    private function resolveUnitCost(Sale $sale, SaleItem $item): ?float
    {
        if ($sale->getTipoId() === 1) {
            $fromItem = $item->getPrecioUnitario();
            return $fromItem !== null ? (float) $fromItem : null;
        }

        $purchasePrice = $item->getProducto()?->getPurchasePrice();
        return $purchasePrice !== null ? (float) $purchasePrice : null;
    }

    private function deterministicUuid(string $value): string
    {
        $hex = md5($value);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function shouldTrackInventory(Product $product): bool
    {
        return $product->getTrackInventory();
    }

    private function shouldReadFromStockItems(): bool
    {
        if ($this->settingsService === null) {
            return false;
        }

        return $this->readBooleanSetting(Constants::INVENTORY_CORE_READ_FROM_STOCK_ITEMS, false);
    }

    private function shouldDualWriteStockItems(): bool
    {
        if ($this->settingsService === null) {
            return false;
        }

        $dualWrite = $this->readBooleanSetting(Constants::INVENTORY_CORE_DUAL_WRITE, false);
        if ($dualWrite) {
            return true;
        }

        $mode = strtolower(trim((string) $this->settingsService->get(Constants::INVENTORY_CORE_MODE, 'legacy')));

        return in_array($mode, ['dual_write', 'v2_authoritative'], true);
    }

    private function readBooleanSetting(string $key, bool $default): bool
    {
        $raw = $this->settingsService?->get($key, $default ? 'true' : 'false');
        if (is_bool($raw)) {
            return $raw;
        }

        return filter_var((string) $raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @throws Exception
     */
    private function hasColumn(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = :table
  AND column_name = :column
SQL,
            ['table' => $table, 'column' => $column]
        ) > 0;
    }

    /**
     * @throws Exception
     */
    private function resolveMovementUnitId(string $productId): ?int
    {
        $unitId = $this->connection->fetchOne(
            <<<'SQL'
SELECT base_unit_id
FROM producto
WHERE producto_id = :product_id
LIMIT 1
SQL,
            ['product_id' => $productId]
        );

        if ($unitId === false || $unitId === null || $unitId === '' || $unitId === '0' || $unitId === 0) {
            // Fallback: use product's own unit (unidad_id)
            $unitId = $this->connection->fetchOne(
                'SELECT unidad_id FROM producto WHERE producto_id = :product_id LIMIT 1',
                ['product_id' => $productId]
            );
        }

        return ($unitId !== false && $unitId !== null && $unitId !== '' && (int) $unitId > 0)
            ? (int) $unitId
            : null;
    }

    /**
     * @throws Exception
     */
    private function syncStockItem(string $warehouseId, string $productId, float $onHand, float $reserved): void
    {
        if (!$this->shouldDualWriteStockItems()) {
            return;
        }

        if (!$this->hasColumn('stock_items', 'qty_on_hand')) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO stock_items (
    warehouse_id,
    product_id,
    qty_on_hand,
    qty_reserved,
    updated_at
) VALUES (
    :warehouse_id,
    :product_id,
    :qty_on_hand,
    :qty_reserved,
    NOW()
)
ON DUPLICATE KEY UPDATE
    qty_on_hand = VALUES(qty_on_hand),
    qty_reserved = VALUES(qty_reserved),
    updated_at = VALUES(updated_at)
SQL,
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'qty_on_hand' => number_format($onHand, 6, '.', ''),
                'qty_reserved' => number_format($reserved, 6, '.', ''),
            ]
        );
    }
}
