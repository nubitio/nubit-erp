<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\ProductStockRepository;
use App\Inventory\Repository\WarehouseRepository;
use App\Service\ProductStockService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProductStockServiceTest extends TestCase
{
    public function testGetStockForProductInWarehouseReturnsZeroWhenNoRecordExists(): void
    {
        $repository = $this->createMock(ProductStockRepository::class);
        $repository->method('findByProductAndWarehouse')->willReturn(null);

        $service = $this->newService(productStockRepository: $repository);

        self::assertSame(0.0, $service->getStockForProductInWarehouse(new Product(), new Warehouse()));
    }

    public function testUpdateStockCreatesMissingProductStockAndUpdatesGlobalStock(): void
    {
        $product = (new Product())->setStock('0.00');
        $warehouse = new Warehouse();

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            }
        );
        $entityManager->expects(self::exactly(2))->method('flush');

        $repository = $this->createMock(ProductStockRepository::class);
        $repository->method('findByProductAndWarehouse')->willReturn(null);
        $repository->method('getTotalStockForProduct')->with($product)->willReturn(15.5);

        $service = $this->newService(entityManager: $entityManager, productStockRepository: $repository);

        $stock = $service->updateStockForProductInWarehouse($product, $warehouse, 10.0);

        self::assertInstanceOf(ProductStock::class, $stock);
        self::assertSame(10.0, $stock->getStock());
        self::assertSame('15.50', $product->getStock());
        self::assertCount(2, $persisted);
    }

    public function testTransferStockRejectsInvalidOrInsufficientQuantity(): void
    {
        $product = new Product();
        $source = new Warehouse();
        $target = new Warehouse();

        $repository = $this->createMock(ProductStockRepository::class);
        $sourceStock = (new ProductStock())->setProduct($product)->setWarehouse($source)->setStock(3.0);
        $repository->method('findByProductAndWarehouse')->willReturnMap([
            [$product, $source, $sourceStock],
            [$product, $target, null],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error');

        $service = $this->newService(productStockRepository: $repository, logger: $logger);

        self::assertFalse($service->transferStock($product, $source, $target, 0));
        self::assertFalse($service->transferStock($product, $source, $target, 5));
    }

    public function testTransferStockMovesStockAndLogsSuccess(): void
    {
        $product = new Product();
        $source = new Warehouse();
        $target = new Warehouse();

        $sourceStock = (new ProductStock())->setProduct($product)->setWarehouse($source)->setStock(8.0);
        $targetStock = (new ProductStock())->setProduct($product)->setWarehouse($target)->setStock(2.0);

        $repository = $this->createMock(ProductStockRepository::class);
        $repository->method('findByProductAndWarehouse')->willReturnMap([
            [$product, $source, $sourceStock],
            [$product, $target, $targetStock],
        ]);
        $repository->method('getTotalStockForProduct')->willReturn(10.0);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeast(2))->method('persist');
        $entityManager->expects(self::atLeast(2))->method('flush');

        $service = $this->newService(
            entityManager: $entityManager,
            productStockRepository: $repository,
            logger: $logger
        );

        self::assertTrue($service->transferStock($product, $source, $target, 3.0));
        self::assertSame(5.0, $sourceStock->getStock());
        self::assertSame(5.0, $targetStock->getStock());
    }

    public function testInitializeAndDistributeStockAcrossWarehouses(): void
    {
        $product = (new Product())->setStock('5');
        $warehouseA = new Warehouse();
        $warehouseB = new Warehouse();

        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $warehouseRepository->method('findAll')->willReturn([$warehouseA, $warehouseB]);

        $repository = $this->createMock(ProductStockRepository::class);
        $repository->method('findByProductAndWarehouse')->willReturn(null);
        $repository->method('getTotalStockForProduct')->willReturn(5.0);

        $stocks = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(
            static function (object $entity) use (&$stocks): void {
                if ($entity instanceof ProductStock) {
                    $stocks[] = $entity->getStock();
                }
            }
        );

        $service = $this->newService(
            entityManager: $entityManager,
            productStockRepository: $repository,
            warehouseRepository: $warehouseRepository
        );

        $service->initializeStockForProduct($product, 4);
        $service->distributeGlobalStockToWarehouses($product);

        self::assertContains(4.0, $stocks);
        self::assertContains(3.0, $stocks);
        self::assertContains(2.0, $stocks);
    }

    private function newService(
        ?EntityManagerInterface $entityManager = null,
        ?ProductStockRepository $productStockRepository = null,
        ?WarehouseRepository $warehouseRepository = null,
        ?LoggerInterface $logger = null,
    ): ProductStockService {
        return new ProductStockService(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $productStockRepository ?? $this->createMock(ProductStockRepository::class),
            $warehouseRepository ?? $this->createMock(WarehouseRepository::class),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }
}
