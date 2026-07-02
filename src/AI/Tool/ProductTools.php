<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Dto\ProductSearchResultDto;
use App\AI\Dto\ProductStockDto;
use App\AI\Dto\ProductWarehouseStockDto;
use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductRepository;
use App\Inventory\Repository\WarehouseRepository;
use App\Inventory\Service\ProductStockService;
use Nubit\Platform\Exception\ServiceException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'search_products',
    description: 'Search products by name or code and return matches with price and stock.',
    method: 'searchProducts',
)]
#[AsTool(
    name: 'product_stock',
    description: 'Return total and per-warehouse stock for a product.',
    method: 'productStock',
)]
final readonly class ProductTools
{
    public function __construct(
        private ProductRepository $productRepository,
        private WarehouseRepository $warehouseRepository,
        private ProductStockService $productStockService,
    ) {}

    /**
     * @return ProductSearchResultDto[]
     */
    public function searchProducts(string $term, int $limit = 5): array
    {
        return $this->productRepository->searchByTerm($term, $limit);
    }

    public function productStock(string $productId): ProductStockDto
    {
        /** @var Product|null $product */
        $product = $this->productRepository->find($productId);
        if (!$product instanceof Product) {
            throw ServiceException::create('Producto no encontrado.');
        }

        $warehouses = [];
        $totalStock = 0.0;

        foreach ($this->warehouseRepository->findAll() as $warehouse) {
            $stock = $this->productStockService->getStockForProductInWarehouse($product, $warehouse);
            $totalStock += $stock;

            $warehouses[] = new ProductWarehouseStockDto(
                (string) $warehouse->getId(),
                $warehouse->getName(),
                $stock,
                (float) ($product->getMinStock() ?? 0),
            );
        }

        return new ProductStockDto(
            (string) $product->getId(),
            $product->getCode(),
            $product->getName(),
            $totalStock,
            $warehouses,
        );
    }
}
