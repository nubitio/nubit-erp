<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Catalog\Entity\Category;
use App\Contacts\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Catalog\Entity\PaymentMethod;
use App\Entity\Product;
use App\Contacts\Entity\Supplier;
use App\Catalog\Entity\UnitOfMeasure;
use App\Entity\Warehouse;
use App\Catalog\Repository\CategoryRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Repository\ProductRepository;
use App\Contacts\Repository\SupplierRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Repository\WarehouseRepository;
use App\Service\TestDataSeeder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
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

    public function testSeedProductsCreatesProductsAndStocksUsingReloadedEntities(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(4))->method('persist');
        $entityManager->expects(self::exactly(3))->method('flush');
        $entityManager->expects(self::exactly(3))->method('clear');

        $category = (new Category())->setName('General');
        $unit = (new UnitOfMeasure())->setName('Unidad')->setCode('NIU')->setType(1)->setRatio('1.00');
        $warehouse = (new Warehouse())->setName('Main');
        $this->setEntityId($category, 'id', 'cat-1');
        $this->setEntityId($unit, 'id', 1);
        $this->setEntityId($warehouse, 'id', 'wh-1');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('findOneBy')->willReturn(null);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->method('find')->willReturn($category);
        $unitRepository = $this->createMock(UnitOfMeasureRepository::class);
        $unitRepository->method('find')->willReturn($unit);
        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $warehouseRepository->method('find')->willReturn($warehouse);
        $warehouseRepository->method('findOneBy')->willReturn($warehouse);

        $seeder = $this->newSeeder(
            entityManager: $entityManager,
            warehouseRepository: $warehouseRepository,
            unitOfMeasureRepository: $unitRepository,
            categoryRepository: $categoryRepository,
            productRepository: $productRepository,
        );

        $created = $this->invokePrivate($seeder, 'seedProducts', [2, [$category], [$unit], $warehouse, 1]);
        self::assertSame(2, $created);
    }

    public function testSeedSalesCreatesSaleAndTaxDocument(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeast(3))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');
        $entityManager->expects(self::exactly(2))->method('clear');

        $customer = (new Customer())->setRuc('20100000001')->setBusinessName('Cliente');
        $supplier = (new Supplier())->setRuc('20400000001')->setBusinessName('Proveedor');
        $paymentMethod = (new PaymentMethod())->setName('Efectivo');
        $product = (new Product())
            ->setCode('P-1')
            ->setType(1)
            ->setName('Producto')
            ->setCategory((new Category())->setName('General'))
            ->setUnitOfMeasure((new UnitOfMeasure())->setName('Unidad')->setCode('NIU')->setType(1)->setRatio('1.00'))
            ->setSalePrice('10.00');
        $warehouse = (new Warehouse())->setName('Main');
        $operation = (new LogisticOperation())->setName('Venta')->setType(2);

        $this->setEntityId($customer, 'id', 1);
        $this->setEntityId($supplier, 'id', 'sup-1');
        $this->setEntityId($paymentMethod, 'id', 1);
        $this->setEntityId($product, 'id', 'prod-1');
        $this->setEntityId($warehouse, 'id', 'wh-1');
        $this->setEntityId($operation, 'id', 'op-1');

        $customerRepository = $this->createMock(CustomerRepository::class);
        $customerRepository->method('find')->willReturn($customer);
        $supplierRepository = $this->createMock(SupplierRepository::class);
        $supplierRepository->method('find')->willReturn($supplier);
        $paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $paymentMethodRepository->method('find')->willReturn($paymentMethod);
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('find')->willReturn($product);
        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $warehouseRepository->method('find')->willReturn($warehouse);
        $warehouseRepository->method('findOneBy')->willReturn($warehouse);
        $logisticRepository = $this->createMock(LogisticOperationRepository::class);
        $logisticRepository->method('find')->willReturn($operation);

        $seeder = $this->newSeeder(
            entityManager: $entityManager,
            warehouseRepository: $warehouseRepository,
            paymentMethodRepository: $paymentMethodRepository,
            logisticOperationRepository: $logisticRepository,
            supplierRepository: $supplierRepository,
            customerRepository: $customerRepository,
            productRepository: $productRepository,
        );

        $created = $this->invokePrivate(
            $seeder,
            'seedSales',
            [1, [$customer], [$product], [$supplier], $warehouse, [$paymentMethod], $operation, 1]
        );
        self::assertSame(1, $created);
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
    ): TestDataSeeder {
        /** @var WarehouseRepository&MockObject $warehouseRepository */
        $warehouseRepository = $warehouseRepository ?? $this->createMock(WarehouseRepository::class);
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
        );
    }

    private function setEntityId(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($entity);
        $prop = $reflection->getProperty($property);
        $prop->setValue($entity, $value);
    }
}
