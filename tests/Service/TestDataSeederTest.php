<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\LogisticOperation;
use App\Entity\Warehouse;
use App\Repository\CategoryRepository;
use App\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use App\Repository\SupplierRepository;
use App\Repository\UnitOfMeasureRepository;
use App\Repository\WarehouseRepository;
use App\Service\TestDataSeeder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TestDataSeederTest extends TestCase
{
    public function testGetFirstWarehouseThrowsWhenNoWarehouseExists(): void
    {
        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $warehouseRepository->method('findOneBy')->willReturn(null);

        $seeder = $this->newSeeder(warehouseRepository: $warehouseRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No warehouse found');
        $this->invokePrivate($seeder, 'getFirstWarehouse');
    }

    public function testGetSaleLogisticOperationThrowsWhenVentaOperationMissing(): void
    {
        $logisticRepository = $this->createMock(LogisticOperationRepository::class);
        $logisticRepository->method('findOneBy')->willReturn(null);

        $seeder = $this->newSeeder(logisticOperationRepository: $logisticRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Venta operation not found');
        $this->invokePrivate($seeder, 'getSaleLogisticOperation');
    }

    public function testSeedCategoriesCreatesAllParentsAndChildrenWhenMissing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(34))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->method('findOneBy')->willReturn(null);

        $seeder = $this->newSeeder(
            entityManager: $entityManager,
            categoryRepository: $categoryRepository
        );

        $created = $this->invokePrivate($seeder, 'seedCategories');
        self::assertSame(34, $created);
    }

    public function testSeedUnitsCreatesAllMissingCodes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(9))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $unitRepository = $this->createMock(UnitOfMeasureRepository::class);
        $unitRepository->method('findOneBy')->willReturn(null);

        $seeder = $this->newSeeder(
            entityManager: $entityManager,
            unitOfMeasureRepository: $unitRepository
        );

        $created = $this->invokePrivate($seeder, 'seedUnitsOfMeasure');
        self::assertSame(9, $created);
    }

    public function testSeedSuppliersAndCustomersCreateRowsWithBatchFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(6))->method('persist');
        $entityManager->expects(self::exactly(4))->method('flush');
        $entityManager->expects(self::exactly(4))->method('clear');

        $supplierRepository = $this->createMock(SupplierRepository::class);
        $supplierRepository->method('findOneBy')->willReturn(null);
        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('findOneBy')->willReturn(null);

        $seeder = $this->newSeeder(
            entityManager: $entityManager,
            supplierRepository: $supplierRepository,
            customerRepository: $customerRepository
        );

        $suppliers = $this->invokePrivate($seeder, 'seedSuppliers', [3, 2]);
        $customers = $this->invokePrivate($seeder, 'seedCustomers', [3, 2]);

        self::assertSame(3, $suppliers);
        self::assertSame(3, $customers);
    }

    /**
     * @param list<mixed> $args
     */
    private function invokePrivate(object $service, string $methodName, array $args = []): mixed
    {
        $method = new \ReflectionMethod($service, $methodName);

        return $method->invokeArgs($service, $args);
    }

    private function newSeeder(
        ?EntityManagerInterface $entityManager = null,
        ?WarehouseRepository $warehouseRepository = null,
        ?UnitOfMeasureRepository $unitOfMeasureRepository = null,
        ?CategoryRepository $categoryRepository = null,
        ?PaymentMethodRepository $paymentMethodRepository = null,
        ?LogisticOperationRepository $logisticOperationRepository = null,
        ?SupplierRepository $supplierRepository = null,
        ?CustomerRepository $customerRepository = null,
        ?ProductRepository $productRepository = null,
        ?SaleRepository $saleRepository = null,
    ): TestDataSeeder {
        $warehouseRepository ??= $this->createMock(WarehouseRepository::class);
        $warehouseRepository->method('findOneBy')->willReturn(new Warehouse());

        return new TestDataSeeder(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $warehouseRepository,
            $unitOfMeasureRepository ?? $this->createMock(UnitOfMeasureRepository::class),
            $categoryRepository ?? $this->createMock(CategoryRepository::class),
            $paymentMethodRepository ?? $this->createMock(PaymentMethodRepository::class),
            $logisticOperationRepository ?? $this->createMock(LogisticOperationRepository::class),
            $supplierRepository ?? $this->createMock(SupplierRepository::class),
            $customerRepository ?? $this->createMock(CustomerRepository::class),
            $productRepository ?? $this->createMock(ProductRepository::class),
            $saleRepository ?? $this->createMock(SaleRepository::class),
        );
    }
}
