<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\ServiceException;
use App\Core\Service\SettingsService;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Warehouse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

/**
 * Ledger-first inventory service.
 *
 * Source of truth: stock_movements
 * Read model: stock_balances
 * Compatibility projection: product_stock / producto.stock
 */
final readonly class InventoryLedgerService
{
    public function __construct(
        private Connection $connection,
        private SettingsService $settings,
    ) {
    }

    /**
     * @throws Exception
     */
    public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float
    {
        $warehouseId = (string) $warehouse->getId();
        $productId = (string) $product->getId();

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
        if ($product->getType() !== 1) {
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

        $this->connection->beginTransaction();
        try {
            try {
                $this->insertStockMovement(
                    sale: $sale,
                    item: $item,
                    movementType: $movementType,
                    delta: $delta,
                    unitCost: $unitCost,
                    sourceType: $sourceType,
                    sourceId: $sourceId,
                    idempotencyKey: $idempotencyKey
                );
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

            if (!$this->isLegacyStockProjectionDisabled()) {
                $this->syncLegacyProjection(
                    warehouseId: (string) $warehouse->getId(),
                    product: $product
                );
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
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
    ): void {
        $warehouseId = (string) $sale->getAlmacen()?->getId();
        $productId = (string) $item->getProducto()?->getId();
        $occurredAt = ($sale->getFecha() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $totalCost = $unitCost !== null ? round(abs($delta) * $unitCost, 2) : null;

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO stock_movements (
    warehouse_id,
    product_id,
    variant_id,
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
    NULL,
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
            [
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
            ]
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
    }

    /**
     * @throws Exception
     */
    private function syncLegacyProjection(string $warehouseId, Product $product): void
    {
        $productId = (string) $product->getId();
        $stockByWarehouse = $this->connection->fetchOne(
            <<<'SQL'
SELECT on_hand_qty
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
        $warehouseStock = $stockByWarehouse !== false ? (float) $stockByWarehouse : 0.0;

        $minStock = (float) ($product->getMinStock() ?? 0.0);
        $exists = $this->connection->fetchOne(
            <<<'SQL'
SELECT 1
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

        if ($exists === false) {
            $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO product_stock (
    product_id,
    warehouse_id,
    stock,
    min_stock,
    created_at,
    updated_at
) VALUES (
    :product_id,
    :warehouse_id,
    :stock,
    :min_stock,
    NOW(),
    NOW()
)
SQL,
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'stock' => number_format($warehouseStock, 3, '.', ''),
                    'min_stock' => number_format($minStock, 3, '.', ''),
                ]
            );
        } else {
            $this->connection->executeStatement(
                <<<'SQL'
UPDATE product_stock
SET stock = :stock,
    min_stock = :min_stock,
    updated_at = NOW()
WHERE warehouse_id = :warehouse_id
  AND product_id = :product_id
SQL,
                [
                    'stock' => number_format($warehouseStock, 3, '.', ''),
                    'min_stock' => number_format($minStock, 3, '.', ''),
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                ]
            );
        }

        $globalStock = $this->connection->fetchOne(
            <<<'SQL'
SELECT COALESCE(SUM(stock), 0)
FROM product_stock
WHERE product_id = :product_id
SQL,
            ['product_id' => $productId]
        );

        $this->connection->executeStatement(
            'UPDATE producto SET stock = :stock WHERE producto_id = :product_id',
            [
                'stock' => number_format((float) ($globalStock !== false ? $globalStock : 0), 3, '.', ''),
                'product_id' => $productId,
            ]
        );
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

    private function isLegacyStockProjectionDisabled(): bool
    {
        return filter_var(
            $this->settings->get('sales_v2_disable_legacy_stock_projection', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
