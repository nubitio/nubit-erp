<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Inventory\Repository\StockMovementRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Throwable;

#[AsTool(
    name: 'inventory_movements',
    description: 'Recent kardex / stock movements for a product. Pass the product UUID. '
    . 'Returns type, quantities, warehouse, and when it happened.',
    method: 'inventoryMovements',
)]
final readonly class InventoryMovementTools
{
    public function __construct(
        private StockMovementRepository $stockMovementRepository,
    ) {}

    /**
     * @return array{error: string}|list<array{
     *     id: string,
     *     occurred_at: string|null,
     *     type: string|null,
     *     qty_in: string|null,
     *     qty_out: string|null,
     *     warehouse: string|null,
     *     source: string|null
     * }>
     */
    public function inventoryMovements(string $productId, int $limit = 10): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['error' => 'Indica el identificador del producto.'];
        }

        $limit = max(1, min(50, $limit));

        try {
            $rows = $this->stockMovementRepository->findKardex(['product' => $productId], 1, $limit);
        } catch (Throwable $throwable) {
            return ['error' => 'No se pudieron leer los movimientos de stock.'];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => $row->id,
                'occurred_at' => $row->occurredAt,
                'type' => $row->movementTypeLabel ?? $row->movementType,
                'qty_in' => $row->qtyIn,
                'qty_out' => $row->qtyOut,
                'warehouse' => $row->warehouse['name'] ?? null,
                'source' => $row->sourceId,
            ];
        }

        return $items;
    }
}
