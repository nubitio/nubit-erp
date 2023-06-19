<?php

namespace App\Tests\Product\Domain;

use App\Category\Domain\Category;
use App\Media\Domain\Media;
use App\Product\Domain\CreateProductUseCase;
use App\Product\Domain\Product;
use App\Product\Domain\ProductRepositoryInterface;
use App\UnitOfMeasure\Domain\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

class ProductCreateUseCaseTest extends TestCase
{
    public function testInvoke(): void
    {
        $productRepository = $this->createConfiguredMock(ProductRepositoryInterface::class, []);
        $productRepository->expects($this->once())
            ->method('insert')
            ->with($this->isInstanceOf(Product::class));

        $productCreateUseCase = new CreateProductUseCase($productRepository);
        $product = new Product(
            '1',
            'Product code',
            2,
            'Product description',
            new Category('1', 'Category name'),
            new UnitOfMeasure(1, 'Unit'),
            10,
            10,
            10.0,
            10.0,
            new Media('1', 'Media name'),
            true,
        );

        $productCreateUseCase($product);

        $this->assertTrue(true);
    }
}
