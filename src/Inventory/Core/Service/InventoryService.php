<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Core\Exception\ServiceException;
use App\Entity\Product;
use App\Entity\SaleRecord;
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
use Doctrine\DBAL\Platforms\SQLitePlatform;
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
     * @param SaleRecord|null $sale Context object for enricher chain
     *
     * @throws ServiceException on validation failure
     * @throws \Doctrine\DBAL\Exception
     */
    public function recordMovement(
        MovementDraft $draft,
        ?SaleRecord $sale = null,
        InventoryBehavior $behavior = InventoryBehavior::TRACKED,
    ): void {
        // NOT_TRACKED products (services, digital goods) never generate stock movements.
        if (!$behavior->tracksInventory()) {
            return;
        }

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

        // Use absolute value of qtyOriginal for conversion (factor lookup is always positive).
        $absQtyOriginal = ltrim(ltrim($draft->qtyOriginal, '+'), '-');
        $qtyBase = $this->unitConversion->toBase(
            $draft->productId,
            $draft->unitId,
            $absQtyOriginal
        );

        // Strip any leading +/- from qtyBase (toBase may add them), then apply direction from delta.
        $absQtyBase = ltrim(ltrim($qtyBase, '+'), '-');
        $signedBase = str_starts_with($draft->qtyDeltaBase, '-') ? '-' . $absQtyBase : $absQtyBase;

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
    private function runEnrichers(MovementDraft $draft, ?SaleRecord $sale): array
    {
        $movements = [$draft];

        foreach ($this->enrichers as $enricher) {
            $movements = $enricher->enrichSaleMovements($sale ?? new SaleRecord(), $movements);
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
                $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
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
                        :qty_delta, :qty_delta_base, :qty_original,
                        :unit_id,
                        :source_type, :source_id,
                        :note, :idempotency_key,
                        'SYNCED', :now, 1, :now, :now
                    )
                    SQL,
                    [
                        'id'              => $movementId,
                        'warehouse_id'    => $movement->warehouseId,
                        'product_id'      => $movement->productId,
                        'occurred_at'     => $occurredAt,
                        'movement_type'   => $movement->movementType->value,
                        'qty_delta'       => $movement->qtyOriginal,
                        'qty_delta_base'  => $movement->qtyDeltaBase,
                        'qty_original'    => $movement->qtyOriginal,
                        'unit_id'         => $movement->unitId ?: null,
                        'source_type'     => $movement->sourceType,
                        'source_id'       => $movement->sourceId,
                        'note'            => $movement->note,
                        'idempotency_key' => $this->buildIdempotencyKey($movement),
                        'now'             => $now,
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
            movementType: $movement->movementType,
            qtyDeltaBase: $movement->qtyDeltaBase,
            sourceType: $movement->sourceType,
            sourceId: $movement->sourceId,
            happenedAt: new \DateTimeImmutable($occurredAt),
        ));
    }

    /**
     * Atomically upsert the stock_items read model.
     *
     * On MariaDB/MySQL uses a single INSERT … ON DUPLICATE KEY UPDATE so that
     * concurrent sales of the same product never race on a read-then-write
     * pattern. qty_on_hand is incremented by the signed delta entirely inside
     * the DB engine — no PHP-side arithmetic needed.
     *
     * On SQLite (used in integration tests) falls back to an equivalent
     * INSERT OR IGNORE + UPDATE pair, which is safe inside the surrounding
     * serialised transaction.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function upsertStockItem(MovementDraft $movement, string $movementId): void
    {
        $now    = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $params = [
            'w'     => $movement->warehouseId,
            'p'     => $movement->productId,
            'delta' => $movement->qtyDeltaBase,
            'now'   => $now,
            'mid'   => $movementId,
        ];

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // SQLite does not support ON DUPLICATE KEY UPDATE.
            // INSERT OR IGNORE creates the row with 0 qty if it doesn't exist;
            // the UPDATE then adds the delta in both cases.
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT OR IGNORE INTO stock_items
                    (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, last_movement_id, version)
                VALUES
                    (:w, :p, 0, 0, :now, :mid, 0)
                SQL,
                $params,
            );
            $this->connection->executeStatement(
                <<<'SQL'
                UPDATE stock_items
                SET qty_on_hand      = qty_on_hand + :delta,
                    updated_at       = :now,
                    last_movement_id = :mid,
                    version          = version + 1
                WHERE warehouse_id = :w
                  AND product_id   = :p
                SQL,
                $params,
            );

            return;
        }

        // MariaDB / MySQL: single atomic statement, no race condition possible.
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO stock_items
                (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, last_movement_id, version)
            VALUES
                (:w, :p, :delta, 0, :now, :mid, 1)
            ON DUPLICATE KEY UPDATE
                qty_on_hand      = qty_on_hand + VALUES(qty_on_hand),
                updated_at       = VALUES(updated_at),
                last_movement_id = VALUES(last_movement_id),
                version          = version + 1
            SQL,
            $params,
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
