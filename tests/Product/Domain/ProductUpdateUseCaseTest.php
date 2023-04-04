<?php

namespace App\Tests\Product\Domain;

use App\Category\Domain\Category;
use App\Media\Domain\Media;
use App\Product\Domain\Product;
use App\Product\Domain\ProductRepositoryInterface;
use App\Product\Domain\UpdateProductUseCase;
use PHPUnit\Framework\TestCase;

class ProductUpdateUseCaseTest extends TestCase
{
    public function test__invoke(): void
    {
        $productRepository = $this->createConfiguredMock(ProductRepositoryInterface::class, []);
        $productRepository->expects($this->once())
            ->method('update')
            ->with($this->isInstanceOf(Product::class));

        $productUpdateUseCase = new UpdateProductUseCase($productRepository);
        $product = new Product(
            '1',
            'Product code',
            2,
            'Product description',
            new Category('1', 'Category name'),
            '10',
            10,
            10,
            10.0,
            10.0,
            new Media('1', 'Media name'),
            true,
        );
        $productUpdateUseCase($product);

        $this->assertTrue(true);
    }
}
