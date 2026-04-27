<?php

declare(strict_types=1);

namespace App\State\Product;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Service\ProductStockService;
use App\Platform\Feature\Attribute\RequiresFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductStockController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductStockService $productStockService,
    ) {
    }

    #[RequiresFeature('multi_warehouse')]
    public function __invoke(Request $request): Response
    {
        $productId = $request->attributes->get('id') ?? null;
        $warehouseId = $request->attributes->get('warehouseId') ?? null;

        if ($productId === null) {
            return new Response('Product ID is required', Response::HTTP_BAD_REQUEST);
        }

        $product = $this->entityManager->getRepository(Product::class)->find($productId);

        if (!$product instanceof Product) {
            return new Response('Product not found', Response::HTTP_NOT_FOUND);
        }

        // If warehouseId is 'all', return stock for all warehouses
        if ($warehouseId === 'all') {
            return $this->getStockForAllWarehouses($product);
        }

        // Otherwise, get stock for specific warehouse
        if ($warehouseId === null) {
            return new Response('Warehouse ID is required', Response::HTTP_BAD_REQUEST);
        }

        $warehouse = $this->entityManager->getRepository(Warehouse::class)->find($warehouseId);

        if (!$warehouse instanceof Warehouse) {
            return new Response('Warehouse not found', Response::HTTP_NOT_FOUND);
        }

        // Get stock from the ProductStockService
        $stock = $this->productStockService->getStockForProductInWarehouse($product, $warehouse);

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
            ],
            'stock' => $stock,
        ]);
    }

    private function getStockForAllWarehouses(Product $product): Response
    {
        $warehouses = $this->entityManager->getRepository(Warehouse::class)->findAll();
        $stockData = [];

        foreach ($warehouses as $warehouse) {
            $stock = $this->productStockService->getStockForProductInWarehouse($product, $warehouse);

            $stockData[] = [
                'warehouse' => [
                    'id' => $warehouse->getId(),
                    'name' => $warehouse->getName(),
                ],
                'stock' => $stock,
            ];
        }

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'global_stock' => $product->getStock(),
            'warehouses' => $stockData,
        ]);
    }
}
