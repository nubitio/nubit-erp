<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductStock;
use App\Entity\Warehouse;
use App\Repository\ProductStockRepository;
use App\Repository\WarehouseRepository;
use App\Inventory\Core\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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

    private ?InventoryLedgerService $inventoryLedgerService = null;
    private ?InventoryService $inventoryService = null;

    #[Required]
    public function setInventoryService(InventoryService $inventoryService): void
    {
        $this->inventoryService = $inventoryService;
    }

    #[Required]
    public function setInventoryLedgerService(InventoryLedgerService $inventoryLedgerService): void
    {
        $this->inventoryLedgerService = $inventoryLedgerService;
    }

    /**
     * Get stock for a product in a specific warehouse
     */
    public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float
    {
        if ($this->inventoryService instanceof InventoryService && $product->getId() !== null && $warehouse->getId() !== null) {
            return (float) $this->inventoryService->getOnHand((string) $warehouse->getId(), (string) $product->getId());
        }

        if ($this->inventoryLedgerService instanceof InventoryLedgerService && $product->getId() !== null && $warehouse->getId() !== null) {
            return $this->inventoryLedgerService->getStockForProductInWarehouse($product, $warehouse);
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
     * Calculate and update global stock from warehouse-specific stocks
     */
    public function updateGlobalStockForProduct(Product $product): void
    {
        $totalStock = $this->productStockRepository->getTotalStockForProduct($product);

        $product->setStock(number_format($totalStock, 2, '.', ''));
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * Initialize stock for a product in all warehouses
     */
    public function initializeStockForProduct(Product $product, float $initialStock = 0): void
    {
        $warehouses = $this->warehouseRepository->findAll();

        foreach ($warehouses as $warehouse) {
            $this->updateStockForProductInWarehouse($product, $warehouse, $initialStock);
        }
    }

    /**
     * Distribute global stock to warehouses
     */
    public function distributeGlobalStockToWarehouses(Product $product): void
    {
        $globalStock = (float) $product->getStock();
        $warehouses = $this->warehouseRepository->findAll();
        $warehouseCount = count($warehouses);

        if ($warehouseCount === 0) {
            return;
        }

        // Calculate base stock per warehouse
        $baseStock = floor($globalStock / $warehouseCount);
        $remainder = fmod($globalStock, $warehouseCount);

        foreach ($warehouses as $index => $warehouse) {
            $stockForWarehouse = $baseStock;

            // Distribute the remainder to first warehouses
            if ($index < $remainder) {
                $stockForWarehouse++;
            }

            $this->updateStockForProductInWarehouse($product, $warehouse, $stockForWarehouse);
        }
    }
}
