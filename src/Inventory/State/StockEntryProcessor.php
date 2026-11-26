<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\Product;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Entity\User;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Contract\UnitConversionServiceInterface;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockEntryType;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Entity\StockEntry;
use App\Inventory\Entity\StockEntryItem;
use App\Inventory\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Handles POST /stock-entries.
 *
 * Single processor for all four entry types. Each type maps to specific
 * StockMovementType(s) and applies its own validation rules:
 *
 *   RECEIPT    → MANUAL_IN   per item (no stock validation)
 *   ISSUE      → MANUAL_OUT  per item (validates stock if STRICT policy)
 *   TRANSFER   → TRANSFER_OUT (source) + TRANSFER_IN (target) per item
 *   ADJUSTMENT → ADJUSTMENT  per item (qty absolute → delta computed here)
 *
 * All persistence + ledger writes happen inside a single transaction.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class StockEntryProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(
        private InventoryService $inventoryService,
        private UnitConversionServiceInterface $unitConversion,
        private EntityManagerInterface $em,
        private Security $security,
        private StockValidationPolicyInterface $stockValidationPolicy,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private ActiveReferenceGuard $activeReferenceGuard,
        private OperationalBranchGuard $branchGuard,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof StockEntry) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $type = $data->getType();
        if ($type === null) {
            throw new UnprocessableEntityHttpException('type is required.');
        }

        $this->denyUnlessGranted($data, $type);
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Se requiere un usuario para registrar el movimiento.');
        }

        $this->branchGuard->requireBranch(['warehouse' => $data->getWarehouse()?->getBranch()]);
        $this->resolveTransferDestination($data, $type, $user);
        $this->validateEntry($data, $type);
        $data->setCreatedBy($user);

        $this->em->wrapInTransaction(function () use ($data, $type): void {
            $this->em->persist($data);
            $this->em->flush();

            $entryId = (string) $data->getId();

            match ($type) {
                StockEntryType::RECEIPT => $this->processReceipt($data, $entryId),
                StockEntryType::ISSUE => $this->processIssue($data, $entryId),
                StockEntryType::TRANSFER => $this->processTransfer($data, $entryId),
                StockEntryType::ADJUSTMENT => $this->processAdjustment($data, $entryId),
            };
        });

        return $data;
    }

    private function resolveTransferDestination(StockEntry $entry, StockEntryType $type, User $user): void
    {
        $targetId = $entry->getTargetWarehouseId();
        $target = $entry->getTargetWarehouse();
        if (!$type->requiresTargetWarehouse()) {
            if ($targetId !== null || $target !== null) {
                throw new UnprocessableEntityHttpException('El almacén destino solo se usa en traslados.');
            }

            return;
        }

        if ($targetId !== null) {
            if ($target !== null && $target->getId() !== $targetId) {
                throw new UnprocessableEntityHttpException('Indica un único almacén destino.');
            }

            $filters = $this->em->getFilters();
            $enabled = $filters->isEnabled('branch_filter');
            if ($enabled) {
                $filters->suspend('branch_filter');
            }
            try {
                $target = $this->em->find(Warehouse::class, $targetId);
            } finally {
                if ($enabled) {
                    $filters->restore('branch_filter');
                }
            }
        }

        if ($target === null && $targetId === null) {
            return; // validateEntry reports the missing required destination.
        }

        $branch = $target?->getBranch();
        $branchId = $branch?->getId();
        if ($branchId === null || !$branch->getIsActive() || !$user->canOperateInBranch($branchId)) {
            throw new AccessDeniedException('El almacén destino no pertenece a una sucursal operativa autorizada.');
        }

        $entry->setTargetWarehouse($target);
    }

    private function denyUnlessGranted(StockEntry $entry, StockEntryType $type): void
    {
        $permission = 'inventory.adjust';
        if ($type === StockEntryType::TRANSFER) {
            $permission = 'inventory.transfer';
        } elseif ($type === StockEntryType::ADJUSTMENT && $entry->getReason() === 'COUNT_CORRECTION') {
            $permission = 'inventory.cycle_count';
        }

        if (!$this->security->isGranted('perm:' . $permission)) {
            throw new AccessDeniedException(sprintf('Permission "%s" is required.', $permission));
        }
    }

    // ─── Type handlers ────────────────────────────────────────────────────────

    private function processReceipt(StockEntry $entry, string $entryId): void
    {
        $warehouseId = (string) $entry->getWarehouse()?->getId();
        $note = $this->buildNote($entry);

        foreach ($entry->getItems() as $lineNo => $item) {
            $product = $item->getProduct();
            if (!$this->isTracked($product)) {
                continue;
            }

            $qty = $item->getQty();
            $delta = $qty;

            $item->setQtyDelta($delta);

            $this->record(
                $warehouseId,
                $product,
                StockMovementType::MANUAL_IN,
                $delta,
                $item,
                $this->lineSourceId($entryId, $item, $lineNo),
                $note,
            );
        }
    }

    private function processIssue(StockEntry $entry, string $entryId): void
    {
        $warehouseId = (string) $entry->getWarehouse()?->getId();
        $note = $this->buildNote($entry);

        foreach ($entry->getItems() as $lineNo => $item) {
            $product = $item->getProduct();
            if (!$this->isTracked($product)) {
                continue;
            }

            $qty = $item->getQty();
            $delta = '-' . $qty;

            $item->setQtyDelta($delta);

            $this->record(
                $warehouseId,
                $product,
                StockMovementType::MANUAL_OUT,
                $delta,
                $item,
                $this->lineSourceId($entryId, $item, $lineNo),
                $note,
            );
        }
    }

    private function processTransfer(StockEntry $entry, string $entryId): void
    {
        $sourceId = (string) $entry->getWarehouse()?->getId();
        $targetId = (string) $entry->getTargetWarehouse()?->getId();
        $sourceName = $entry->getWarehouse()?->getName() ?? '';
        $targetName = $entry->getTargetWarehouse()?->getName() ?? '';

        foreach ($entry->getItems() as $lineNo => $item) {
            $product = $item->getProduct();
            if (!$this->isTracked($product)) {
                continue;
            }

            $qty = $item->getQty();
            $lineSourceId = $this->lineSourceId($entryId, $item, $lineNo);

            $item->setQtyDelta('-' . $qty); // from source perspective

            $this->record(
                $sourceId,
                $product,
                StockMovementType::TRANSFER_OUT,
                '-' . $qty,
                $item,
                $lineSourceId . ':out',
                'Traslado a ' . $targetName,
            );

            $this->record(
                $targetId,
                $product,
                StockMovementType::TRANSFER_IN,
                $qty,
                $item,
                $lineSourceId . ':in',
                'Traslado desde ' . $sourceName,
            );
        }
    }

    private function processAdjustment(StockEntry $entry, string $entryId): void
    {
        $warehouseId = (string) $entry->getWarehouse()?->getId();
        $reasonNote = $this->buildNote($entry);

        foreach ($entry->getItems() as $lineNo => $item) {
            $product = $item->getProduct();
            if (!$this->isTracked($product)) {
                continue;
            }

            $productId = (string) $product->getId();
            $qtyBefore = $this->inventoryService->getOnHand($warehouseId, $productId);
            $delta = bcsub($item->getQty(), $qtyBefore, 6);

            $item->setQtyBefore($qtyBefore);
            $item->setQtyDelta($delta);

            // Skip if nothing changed
            if (bccomp($delta, '0', 6) === 0) {
                continue;
            }

            $this->record(
                $warehouseId,
                $product,
                StockMovementType::ADJUSTMENT,
                $delta,
                $item,
                $this->lineSourceId($entryId, $item, $lineNo),
                $reasonNote,
            );
        }
    }

    // ─── Stock validation ─────────────────────────────────────────────────────

    /**
     * Validates business rules before persisting.
     * Pre-flight stock checks run here so we can abort before any DB writes.
     */
    private function validateEntry(StockEntry $entry, StockEntryType $type): void
    {
        $warehouse = $entry->getWarehouse();
        if ($warehouse === null) {
            throw new UnprocessableEntityHttpException('warehouse is required.');
        }

        // POST is the only write operation on this resource, so every call here is a create.
        $this->activeReferenceGuard->assertActiveOnCreate($warehouse, 'warehouse', 'El almacén');

        if ($entry->getItems()->isEmpty()) {
            throw new UnprocessableEntityHttpException('At least one item is required.');
        }

        if ($type->requiresTargetWarehouse()) {
            $target = $entry->getTargetWarehouse();
            if ($target === null) {
                throw new UnprocessableEntityHttpException('targetWarehouse is required for TRANSFER entries.');
            }
            if ($target->getId() === $warehouse->getId()) {
                throw new UnprocessableEntityHttpException('sourceWarehouse and targetWarehouse must be different.');
            }
            $this->activeReferenceGuard->assertActiveOnCreate($target, 'targetWarehouse', 'El almacén destino');
        }

        if ($type->requiresReason() && empty($entry->getReason())) {
            throw new UnprocessableEntityHttpException('reason is required for ADJUSTMENT entries.');
        }

        if ($entry->getReason() === 'OTHER' && empty(trim($entry->getNote() ?? ''))) {
            throw new UnprocessableEntityHttpException('note is required when reason is OTHER.');
        }

        if ($type->validatesStock()) {
            $warehouseId = (string) $warehouse->getId();
            $requestedByProduct = [];

            foreach ($entry->getItems() as $item) {
                $product = $item->getProduct();
                if (!$this->isTracked($product)) {
                    continue;
                }

                $productId = (string) $product->getId();
                $requestedByProduct[$productId] ??= ['product' => $product, 'qty' => '0.000000'];
                $requestedByProduct[$productId]['qty'] = bcadd(
                    $requestedByProduct[$productId]['qty'],
                    $this->toBaseQty($item),
                    6,
                );
            }

            foreach ($requestedByProduct as $productId => $request) {
                $onHand = $this->inventoryService->getOnHand($warehouseId, $productId);
                $result = $this->stockValidationPolicy->validateSale(
                    $request['product'],
                    $warehouseId,
                    $request['qty'],
                    $onHand,
                );

                if (!$result->allowed) {
                    throw new UnprocessableEntityHttpException(sprintf(
                        'Stock insuficiente para "%s". Disponible: %s, solicitado: %s.',
                        $request['product']->getName(),
                        $onHand,
                        $request['qty'],
                    ));
                }
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function record(
        string $warehouseId,
        mixed $product,
        StockMovementType $movementType,
        string $delta,
        StockEntryItem $item,
        string $sourceId,
        string $note,
    ): void {
        $this->inventoryService->recordMovement(new MovementDraft(
            warehouseId: $warehouseId,
            productId: (string) $product?->getId(),
            movementType: $movementType,
            qtyDeltaBase: $delta,
            unitId: (int) ($item->getUnit()?->getId() ?? 0),
            qtyOriginal: ltrim(ltrim($delta, '+'), '-'),
            sourceType: 'STOCK_ENTRY',
            sourceId: $sourceId,
            note: $note,
        ));
    }

    private function buildNote(StockEntry $entry): string
    {
        $parts = array_filter([
            $entry->getOperation()?->getName(),
            $entry->getReason() ? $entry->getReasonLabel() : null,
            $entry->getNote(),
        ]);

        return implode(' — ', $parts) ?: $entry->getType()?->label() ?? '';
    }

    private function lineSourceId(string $entryId, StockEntryItem $item, int|string $lineNo): string
    {
        return sprintf('%s:line:%s', $entryId, $item->getId() ?? $lineNo);
    }

    /** @return numeric-string */
    private function toBaseQty(StockEntryItem $item): string
    {
        $unitId = $item->getUnit()?->getId();
        if ($unitId === null) {
            return $item->getQty();
        }

        $qty = $this->unitConversion->toBase((string) $item->getProduct()?->getId(), (int) $unitId, $item->getQty());
        if (!is_numeric($qty)) {
            throw new \UnexpectedValueException('Converted stock quantity is not numeric.');
        }

        return $qty;
    }

    /** @phpstan-assert-if-true =Product $product */
    private function isTracked(?Product $product): bool
    {
        if ($product === null) {
            return false;
        }

        $behavior = InventoryBehavior::tryFrom($product->getInventoryBehavior()) ?? InventoryBehavior::TRACKED;

        return $behavior->tracksInventory();
    }
}
