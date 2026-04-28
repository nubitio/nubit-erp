<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Core\Exception\ServiceException;
use App\Dto\Assistant\ProductSearchResultDto;
use App\Dto\Assistant\ProductStockDto;
use App\Dto\Assistant\ProductWarehouseStockDto;
use App\Catalog\Entity\Product;
use App\Inventory\Entity\ProductStock;
use App\Catalog\Repository\ProductRepository;
use App\Inventory\Repository\ProductStockRepository;
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
        private ProductStockRepository $productStockRepository,
    ) {
    }

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

        $stocks = $this->productStockRepository->findByProduct($product);

        $fallbackStock = $product->getStock();

        return new ProductStockDto(
            (string) $product->getId(),
            $product->getCode(),
            $product->getName(),
            $this->productStockRepository->getTotalStockForProduct($product),
            $fallbackStock !== null ? (float) $fallbackStock : null,
            array_map(static fn (ProductStock $stock): ProductWarehouseStockDto => new ProductWarehouseStockDto(
                (string) $stock->getWarehouse()->getId(),
                $stock->getWarehouse()->getName(),
                $stock->getStock(),
                $stock->getMinStock(),
            ), $stocks),
        );
    }
}
