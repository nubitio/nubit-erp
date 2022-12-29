<?php

namespace App\Tests\Product\Domain;

use App\Product\Domain\Product;
use App\Product\Domain\ProductCreateUseCase;
use App\Product\Domain\ProductRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ProductCreateUseCaseTest extends TestCase
{
    public function test_invoke(): void
    {
        $productRepository = $this->createConfiguredMock(ProductRepositoryInterface::class, []);
        $productRepository->expects($this->once())
            ->method('insert')
            ->with($this->isInstanceOf(Product::class));

        $productCreateUseCase = new ProductCreateUseCase($productRepository);
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

        $productCreateUseCase($product);

        $this->assertTrue(true);
    }
}
