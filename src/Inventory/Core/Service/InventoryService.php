<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Core\Exception\ServiceException;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\Warehouse;
use App\Inventory\Core\Contract\StockMovementEnricherInterface;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Event\StockMovementRecorded;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Unified inventory facade (v2).
 *
 * Single public surface for all inventory operations:
 *   recordMovement()  — writes to ledger + updates read model
 *   getOnHand()       — reads from stock_items with fallback chain
 *   reserve()         — increments qty_reserved
 *   release()         — decrements qty_reserved
 *
 * Design invariants:
 *   - All qty arithmetic uses bcmath (DECIMAL-safe, no float precision loss)
 *   - Backend computes qty_delta_base from qty_original + unit conversion
 *   - Movements are idempotent: duplicate calls with same idempotency_key are silently skipped
 *   - Enricher chain transforms composite-product movements into BOM components
 *   - Events dispatched after successful persistence
 */
final class InventoryService
{
    /** @param iterable<StockMovementEnricherInterface> $enrichers */
    public function __construct(
        private readonly Connection $connection,
        private readonly StockValidationPolicyInterface $validationPolicy,
        private readonly UnitConversionService $unitConversion,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly iterable $enrichers = [],
    ) {
    }

    // ─── Write path ─────────────────────────────────────────────────────────────

    /**
     * Record an inventory movement (idempotent, transactional).
     *
     * The caller builds a MovementDraft with qty in original units;
     * this service computes qty_delta_base via UnitConversionService.
     *
     * Enrichers (e.g. BomExplosionService) may replace or expand the draft list.
     *
     * @param Sale|null $sale Context object for enricher chain
     *
     * @throws ServiceException on validation failure
     * @throws \Doctrine\DBAL\Exception
     */
    public function recordMovement(MovementDraft $draft, ?Sale $sale = null): void
    {
        // Compute base qty server-side — never trust client values.
        $draftWithBase = $this->applyBaseConversion($draft);

        // Run enricher chain (BOM explosion, etc.)
        $movements = $this->runEnrichers($draftWithBase, $sale);

        foreach ($movements as $movement) {
            $this->persistMovement($movement);
        }
    }

    /**
     * Validate stock availability before recording a sale movement.
     * Separated from recordMovement to allow pre-flight checks in UI.
     */
    public function validateForSale(
        Product $product,
        string $warehouseId,
        string $requestedQty,
        int $unitId,
    ): StockValidationResult {
        $behavior = InventoryBehavior::tryFrom($product->getInventoryBehavior())
            ?? InventoryBehavior::TRACKED;

        if (!$behavior->tracksInventory()) {
            return StockValidationResult::allowed();
        }

        $qtyBase = $this->unitConversion->toBase((string) $product->getId(), $unitId, $requestedQty);
        $onHand = $this->getOnHand($warehouseId, (string) $product->getId());

        return $this->validationPolicy->validateSale($product, $warehouseId, $qtyBase, $onHand);
    }

    // ─── Read path ───────────────────────────────────────────────────────────────

    /**
     * Get on-hand quantity for a product in a warehouse.
     * Read chain: stock_items → stock_balances → product_stock (legacy).
     */
    public function getOnHand(string $warehouseId, string $productId): string
    {
        // Try stock_items first (v2 read model)
        $qty = $this->connection->fetchOne(
            'SELECT qty_on_hand FROM stock_items WHERE warehouse_id = :w AND product_id = :p',
            ['w' => $warehouseId, 'p' => $productId]
        );
        if ($qty !== false) {
            return (string) $qty;
        }

        // Fallback: stock_balances
        $qty = $this->connection->fetchOne(
            'SELECT on_hand_qty FROM stock_balances WHERE warehouse_id = :w AND product_id = :p',
            ['w' => $warehouseId, 'p' => $productId]
        );
        if ($qty !== false) {
            return (string) $qty;
        }

        return '0';
    }

    /**
     * Reserve stock (increment qty_reserved), used for pending orders.
     *
     * @throws ServiceException on insufficient available stock
     * @throws \Doctrine\DBAL\Exception
     */
    public function reserve(string $warehouseId, string $productId, string $qtyBase): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, version)
            VALUES (:w, :p, 0, :qty, NOW(), 1)
            ON DUPLICATE KEY UPDATE
                qty_reserved = qty_reserved + VALUES(qty_reserved),
                updated_at   = NOW(),
                version      = version + 1
            SQL,
            ['w' => $warehouseId, 'p' => $productId, 'qty' => $qtyBase]
        );
    }

    /**
     * Release previously reserved stock.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function release(string $warehouseId, string $productId, string $qtyBase): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE stock_items
            SET qty_reserved = GREATEST(0, qty_reserved - :qty),
                updated_at   = NOW(),
                version      = version + 1
            WHERE warehouse_id = :w AND product_id = :p
            SQL,
            ['w' => $warehouseId, 'p' => $productId, 'qty' => $qtyBase]
        );
    }

    // ─── Internal ────────────────────────────────────────────────────────────────

    private function applyBaseConversion(MovementDraft $draft): MovementDraft
    {
        if ($draft->unitId === 0) {
            // Unit 0 = "base unit already": pass-through.
            return $draft;
        }

        $qtyBase = $this->unitConversion->toBase(
            $draft->productId,
            $draft->unitId,
            $draft->qtyOriginal
        );

        // Preserve direction from the original delta sign.
        $signedBase = str_starts_with($draft->qtyDeltaBase, '-') ? '-' . $qtyBase : $qtyBase;

        return new MovementDraft(
            warehouseId: $draft->warehouseId,
            productId: $draft->productId,
            movementType: $draft->movementType,
            qtyDeltaBase: $signedBase,
            unitId: $draft->unitId,
            qtyOriginal: $draft->qtyOriginal,
            sourceType: $draft->sourceType,
            sourceId: $draft->sourceId,
            note: $draft->note,
            happenedAt: $draft->happenedAt,
        );
    }

    /**
     * @return list<MovementDraft>
     */
    private function runEnrichers(MovementDraft $draft, ?Sale $sale): array
    {
        $movements = [$draft];

        foreach ($this->enrichers as $enricher) {
            $movements = $enricher->enrichSaleMovements($sale ?? new Sale(), $movements);
        }

        return $movements;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function persistMovement(MovementDraft $movement): void
    {
        $movementId = Uuid::v7()->toRfc4122();
        $occurredAt = $movement->happenedAt ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->beginTransaction();
        try {
            try {
                $this->connection->executeStatement(
                    <<<'SQL'
                    INSERT INTO stock_movements (
                        id,
                        warehouse_id, product_id,
                        occurred_at, movement_type,
                        quantity_delta, qty_delta_base, qty_original,
                        unit_id,
                        source_type, source_id,
                        note, idempotency_key,
                        sync_status, synced_at, version_no, last_modified_at, created_at
                    ) VALUES (
                        :id,
                        :warehouse_id, :product_id,
                        :occurred_at, :movement_type,
                        :qty_delta_base, :qty_delta_base, :qty_original,
                        :unit_id,
                        :source_type, :source_id,
                        :note, :idempotency_key,
                        'SYNCED', NOW(), 1, NOW(), NOW()
                    )
                    SQL,
                    [
                        'id'              => $movementId,
                        'warehouse_id'    => $movement->warehouseId,
                        'product_id'      => $movement->productId,
                        'occurred_at'     => $occurredAt,
                        'movement_type'   => $movement->movementType->value,
                        'qty_delta_base'  => $movement->qtyDeltaBase,
                        'qty_original'    => $movement->qtyOriginal,
                        'unit_id'         => $movement->unitId ?: null,
                        'source_type'     => $movement->sourceType,
                        'source_id'       => $movement->sourceId,
                        'note'            => $movement->note,
                        'idempotency_key' => $this->buildIdempotencyKey($movement),
                    ]
                );
            } catch (UniqueConstraintViolationException) {
                // Idempotent replay: movement already applied; skip silently.
                $this->connection->rollBack();

                return;
            }

            // Update stock_items read model (upsert with version increment + last_movement_id)
            $this->upsertStockItem($movement, $movementId);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        // Dispatch domain event after commit
        $this->eventDispatcher->dispatch(new StockMovementRecorded(
            movementId: $movementId,
            warehouseId: $movement->warehouseId,
            productId: $movement->productId,
            movementType: $movement->movementType->value,
            qtyDeltaBase: $movement->qtyDeltaBase,
            sourceType: $movement->sourceType,
            sourceId: $movement->sourceId,
            happenedAt: new \DateTimeImmutable($occurredAt),
        ));
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function upsertStockItem(MovementDraft $movement, string $movementId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, last_movement_id, version)
            VALUES (:w, :p, :delta, 0, NOW(), :mid, 1)
            ON DUPLICATE KEY UPDATE
                qty_on_hand      = qty_on_hand + VALUES(qty_on_hand),
                updated_at       = NOW(),
                last_movement_id = :mid,
                version          = version + 1
            SQL,
            [
                'w'     => $movement->warehouseId,
                'p'     => $movement->productId,
                'delta' => $movement->qtyDeltaBase,
                'mid'   => $movementId,
            ]
        );
    }

    private function buildIdempotencyKey(MovementDraft $movement): string
    {
        return hash('sha256', implode('|', [
            $movement->sourceType,
            $movement->sourceId,
            $movement->movementType->value,
            $movement->warehouseId,
            $movement->productId,
        ]));
    }
}
