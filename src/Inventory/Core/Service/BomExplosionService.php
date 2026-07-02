<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductBomItemRepository;
use App\Inventory\Core\Contract\StockMovementEnricherInterface;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enriches sale movements for COMPOSITE products.
 *
 * When a sale line contains a product with inventory_behavior = COMPOSITE:
 *   1. The original movement draft for the parent is REMOVED (composite products
 *      do not move stock themselves).
 *   2. Component movements are ADDED — one MovementDraft per BOM line, scaled
 *      by the sale quantity × wastage_factor.
 *
 * This implements the Open/Closed Principle: core is unaware of BOM details;
 * BomExplosionService is injected via the enricher chain.
 */
final class BomExplosionService implements StockMovementEnricherInterface
{
    public function __construct(
        private readonly ProductBomItemRepository $bomItemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<MovementDraft> $baseMovements
     *
     * @return list<MovementDraft>
     */
    public function enrichSaleMovements(SaleRecord $sale, array $baseMovements): array
    {
        $enriched = [];

        foreach ($baseMovements as $draft) {
            /** @var Product|null $product */
            $product = $this->entityManager->find(Product::class, $draft->productId);

            if ($product === null) {
                $enriched[] = $draft;
                continue;
            }

            $behavior = InventoryBehavior::tryFrom($product->getInventoryBehavior()) ?? InventoryBehavior::TRACKED;

            if (!$behavior->requiresBomExplosion()) {
                $enriched[] = $draft;
                continue;
            }

            // COMPOSITE: replace parent movement with component movements
            $bomLines = $this->bomItemRepository->findByParent($draft->productId);

            foreach ($bomLines as $line) {
                if ($line->isOptional()) {
                    continue; // optional components are skipped by default
                }

                $parentQtyAbs = ltrim($draft->qtyDeltaBase, '-');
                $componentQty = $line->effectiveQty($parentQtyAbs);

                // Preserve the sign from the parent draft (negative = outbound)
                $signedQty = str_starts_with($draft->qtyDeltaBase, '-') ? '-' . $componentQty : $componentQty;

                $enriched[] = new MovementDraft(
                    warehouseId: $draft->warehouseId,
                    productId: (string) $line->getComponentProduct()->getId(),
                    movementType: $draft->movementType,
                    qtyDeltaBase: $signedQty,
                    unitId: $draft->unitId,
                    qtyOriginal: $componentQty,
                    sourceType: $draft->sourceType,
                    sourceId: $draft->sourceId . ':bom:' . $line->getId(),
                    note: sprintf(
                        'BOM explosion from %s (factor×wastage: %s×%s)',
                        $draft->productId,
                        $line->getComponentQtyBase(),
                        $line->getWastageFactor(),
                    ),
                    happenedAt: $draft->happenedAt,
                );
            }
        }

        return $enriched;
    }
}
