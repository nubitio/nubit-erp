<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\ProductStockRepository;
use App\Inventory\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\Attribute\Required;

class ProductStockService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductStockRepository $productStockRepository,
        private WarehouseRepository $warehouseRepository,
        private LoggerInterface $logger,
    ) {
    }

    private ?InventoryService $inventoryService = null;

    #[Required]
    public function setInventoryService(InventoryService $inventoryService): void
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get stock for a product in a specific warehouse
     */
    public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float
    {
        if ($this->inventoryService instanceof InventoryService && $product->getId() !== null && $warehouse->getId() !== null) {
            return (float) $this->inventoryService->getOnHand((string) $warehouse->getId(), (string) $product->getId());
        }

        $productStock = $this->productStockRepository->findByProductAndWarehouse($product, $warehouse);

        return $productStock instanceof ProductStock ? $productStock->getStock() : 0;
    }

    /**
     * Update stock for a product in a warehouse
     */
    public function updateStockForProductInWarehouse(
        Product $product,
        Warehouse $warehouse,
        float $newStock
    ): ProductStock {
        if ($this->inventoryService instanceof InventoryService && $product->getId() !== null && $warehouse->getId() !== null) {
            $currentStock = $this->inventoryService->getOnHand((string) $warehouse->getId(), (string) $product->getId());
            $targetStock = number_format($newStock, 6, '.', '');
            $delta = bcsub($targetStock, $currentStock, 6);

            if (bccomp($delta, '0', 6) !== 0) {
                $this->inventoryService->recordMovement(
                    new MovementDraft(
                        warehouseId: (string) $warehouse->getId(),
                        productId: (string) $product->getId(),
                        movementType: StockMovementType::ADJUSTMENT,
                        qtyDeltaBase: $delta,
                        unitId: 0,
                        qtyOriginal: ltrim(ltrim($delta, '+'), '-'),
                        sourceType: 'PRODUCT_STOCK_ADJUSTMENT',
                        sourceId: Uuid::v7()->toRfc4122(),
                        note: 'Ajuste manual de stock',
                    ),
                    behavior: InventoryBehavior::tryFrom($product->getInventoryBehavior()) ?? InventoryBehavior::TRACKED,
                );
            }

            return $this->buildStockSnapshot($product, $warehouse, $newStock);
        }

        $productStock = $this->productStockRepository->findByProductAndWarehouse($product, $warehouse);

        if (!$productStock instanceof ProductStock) {
            $productStock = new ProductStock();
            $productStock->setProduct($product);
            $productStock->setWarehouse($warehouse);
            $productStock->setMinStock($product->getMinStock() ?? 0);
        }

        $productStock->setStock($newStock);
        $productStock->updateTimestamps();

        $this->entityManager->persist($productStock);
        $this->entityManager->flush();

        // Update the global stock on the product entity
        $this->updateGlobalStockForProduct($product);

        return $productStock;
    }

    /**
     * Transfer stock between warehouses
     */
    public function transferStock(
        Product $product,
        Warehouse $sourceWarehouse,
        Warehouse $targetWarehouse,
        float $quantity
    ): bool {
        if ($quantity <= 0) {
            $this->logger->error('Cannot transfer non-positive quantity', [
                'product' => $product->getId(),
                'quantity' => $quantity,
            ]);

            return false;
        }

        if (
            $this->inventoryService instanceof InventoryService
            && $product->getId() !== null
            && $sourceWarehouse->getId() !== null
            && $targetWarehouse->getId() !== null
        ) {
            $sourceStock = (float) $this->inventoryService->getOnHand(
                (string) $sourceWarehouse->getId(),
                (string) $product->getId(),
            );

            if ($sourceStock < $quantity) {
                $this->logger->error('Insufficient stock for transfer', [
                    'product' => $product->getId(),
                    'sourceWarehouse' => $sourceWarehouse->getId(),
                    'availableStock' => $sourceStock,
                    'requestedQuantity' => $quantity,
                ]);

                return false;
            }

            $transferId = Uuid::v7()->toRfc4122();
            $qty = number_format($quantity, 6, '.', '');
            $behavior = InventoryBehavior::tryFrom($product->getInventoryBehavior()) ?? InventoryBehavior::TRACKED;

            $this->inventoryService->recordMovement(
                new MovementDraft(
                    warehouseId: (string) $sourceWarehouse->getId(),
                    productId: (string) $product->getId(),
                    movementType: StockMovementType::TRANSFER_OUT,
                    qtyDeltaBase: '-' . $qty,
                    unitId: 0,
                    qtyOriginal: $qty,
                    sourceType: 'PRODUCT_STOCK_TRANSFER',
                    sourceId: $transferId . ':out',
                    note: 'Traslado a ' . $targetWarehouse->getName(),
                ),
                behavior: $behavior,
            );

            $this->inventoryService->recordMovement(
                new MovementDraft(
                    warehouseId: (string) $targetWarehouse->getId(),
                    productId: (string) $product->getId(),
                    movementType: StockMovementType::TRANSFER_IN,
                    qtyDeltaBase: $qty,
                    unitId: 0,
                    qtyOriginal: $qty,
                    sourceType: 'PRODUCT_STOCK_TRANSFER',
                    sourceId: $transferId . ':in',
                    note: 'Traslado desde ' . $sourceWarehouse->getName(),
                ),
                behavior: $behavior,
            );

            $this->logger->info('Stock transferred successfully', [
                'product' => $product->getId(),
                'sourceWarehouse' => $sourceWarehouse->getId(),
                'targetWarehouse' => $targetWarehouse->getId(),
                'quantity' => $quantity,
            ]);

            return true;
        }

        $sourceStock = $this->getStockForProductInWarehouse($product, $sourceWarehouse);

        if ($sourceStock < $quantity) {
            $this->logger->error('Insufficient stock for transfer', [
                'product' => $product->getId(),
                'sourceWarehouse' => $sourceWarehouse->getId(),
                'availableStock' => $sourceStock,
                'requestedQuantity' => $quantity,
            ]);

            return false;
        }

        $targetStock = $this->getStockForProductInWarehouse($product, $targetWarehouse);

        $this->updateStockForProductInWarehouse($product, $sourceWarehouse, $sourceStock - $quantity);
        $this->updateStockForProductInWarehouse($product, $targetWarehouse, $targetStock + $quantity);

        $this->logger->info('Stock transferred successfully', [
            'product' => $product->getId(),
            'sourceWarehouse' => $sourceWarehouse->getId(),
            'targetWarehouse' => $targetWarehouse->getId(),
            'quantity' => $quantity,
        ]);

        return true;
    }

    /**
     * @deprecated Removed. producto.stock column no longer exists (dropped in
     *             Version20260430000001_drop_producto_stock_column).
     */
    public function updateGlobalStockForProduct(Product $product): void
    {
        // No-op: producto.stock column no longer exists.
    }

    /**
     * @deprecated No callers in production code. Seeding uses TestDataSeeder/E2eTestSeedService directly.
     *             Remove once migration commands are retired.
     */
    public function initializeStockForProduct(Product $product, float $initialStock = 0): void
    {
        $warehouses = $this->warehouseRepository->findAll();

        foreach ($warehouses as $warehouse) {
            $this->updateStockForProductInWarehouse($product, $warehouse, $initialStock);
        }
    }

    /**
     * @deprecated Removed. The producto.stock column was dropped in
     *             Version20260430000001_drop_producto_stock_column.
     *             Stock migration is now handled by the Doctrine migration directly.
     */
    public function distributeGlobalStockToWarehouses(Product $product): void
    {
        // No-op: producto.stock column no longer exists.
    }

    private function buildStockSnapshot(Product $product, Warehouse $warehouse, float $stock): ProductStock
    {
        $productStock = new ProductStock();
        $productStock->setProduct($product);
        $productStock->setWarehouse($warehouse);
        $productStock->setMinStock((float) ($product->getMinStock() ?? 0));
        $productStock->setStock($stock);
        $productStock->updateTimestamps();

        return $productStock;
    }
}
