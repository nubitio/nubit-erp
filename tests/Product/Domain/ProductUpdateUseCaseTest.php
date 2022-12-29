<?php

namespace App\Tests\Product\Domain;

use App\Product\Domain\Product;
use App\Product\Domain\ProductRepositoryInterface;
use App\Product\Domain\ProductUpdateUseCase;
use PHPUnit\Framework\TestCase;

class ProductUpdateUseCaseTest extends TestCase
{
    public function test__invoke(): void
    {
        $productRepository = $this->createConfiguredMock(ProductRepositoryInterface::class, []);
        $productRepository->expects($this->once())
            ->method('update')
            ->with($this->isInstanceOf(Product::class));

        $productUpdateUseCase = new ProductUpdateUseCase($productRepository);
        $product = new Product(
            1,
            'Product code',
            2,
            'Product description',
            10,
            10,
            10,
            10,
            10,
            10,
            '/path/to/image',
            1,
        );
        $productUpdateUseCase($product);

        $this->assertTrue(true);
    }
}
