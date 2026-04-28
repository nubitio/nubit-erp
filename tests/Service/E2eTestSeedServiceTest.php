<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Setup\Dto\SetupStatusDto;
use App\Organization\Entity\Branch;
use App\CashRegister\Entity\CashRegister;
use App\Catalog\Entity\Category;
use App\Contacts\Entity\Customer;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Identity\Entity\Role;
use App\Sales\Entity\SaleRecord;
use App\Catalog\Entity\UnitOfMeasure;
use App\Identity\Entity\User;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Entity\VoucherSeries;
use App\Inventory\Entity\Warehouse;
use App\Organization\Repository\BranchRepository;
use App\CashRegister\Repository\CashRegisterRepository;
use App\Catalog\Repository\CategoryRepository;
use App\Organization\Repository\CompanyRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\ProductRepository;
use App\Inventory\Repository\ProductStockRepository;
use App\Identity\Repository\RoleRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Identity\Repository\UserRepository;
use App\Catalog\Repository\VoucherSeriesRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Inventory\Repository\WarehouseRepository;
use App\Setup\Service\E2eTestSeedService;
use App\Setup\InitialSetupService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class E2eTestSeedServiceTest extends TestCase
{
    public function testSeedBuildsUserAndSalePayloadWhenBaseEntitiesExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $initialSetupService = $this->createMock(InitialSetupService::class);
        $companyRepository = $this->createMock(CompanyRepository::class);
        $roleRepository = $this->createMock(RoleRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $branchRepository = $this->createMock(BranchRepository::class);
        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $customerRepository = $this->createMock(CustomerRepository::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $productStockRepository = $this->createMock(ProductStockRepository::class);
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $unitRepository = $this->createMock(UnitOfMeasureRepository::class);
        $voucherTypeRepository = $this->createMock(VoucherTypeRepository::class);
        $voucherSeriesRepository = $this->createMock(VoucherSeriesRepository::class);
        $paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $cashRegisterRepository = $this->createMock(CashRegisterRepository::class);

        $initialSetupService->method('status')->willReturn(new SetupStatusDto(true, 1, 1, 1));

        $role = (new Role())->setName('Cajero');
        $this->setEntityId($role, 'id', 3);
        $roleRepository->method('find')->willReturnMap([
            [3, $role],
            [2, null],
            [1, null],
        ]);

        $user = (new User())->setUsername('ci_e2e_user');
        $this->setEntityId($user, 'id', 5);
        $userRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?User => ($criteria['username'] ?? null) === 'ci_e2e_user' ? $user : null
        );
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');

        $branch = (new Branch())->setName('CI E2E Branch');
        $warehouse = (new Warehouse())->setName('Almacen CI E2E')->setBranch($branch);
        $branchRepository->method('findOneBy')->willReturn($branch);
        $warehouseRepository->method('findOneBy')->willReturn($warehouse);

        $cashRegister = (new CashRegister())->setName('Caja CI E2E')->setBranch($branch);
        $this->setEntityId($cashRegister, 'id', 1);
        $cashRegisterRepository->method('findOneBy')->willReturn($cashRegister);

        $voucherType1 = (new VoucherType())->setCode('01')->setName('Factura');
        $voucherType3 = (new VoucherType())->setCode('03')->setName('Boleta');
        $voucherTypeRepository->method('find')->willReturnMap([
            ['01', $voucherType1],
            ['03', $voucherType3],
        ]);
        $voucherSeriesRepository->method('findOneBy')->willReturn(new VoucherSeries());

        $customer = (new Customer())->setRuc('20100000001')->setBusinessName('Cliente CI E2E');
        $customerRepository->method('findOneBy')->willReturn($customer);

        $product = (new Product())
            ->setCode('E2E-CI-PRODUCT')
            ->setType(1)
            ->setName('Producto CI E2E')
            ->setCategory((new Category())->setName('General'))
            ->setUnitOfMeasure((new UnitOfMeasure())->setName('Unidad')->setCode('NIU')->setType(1)->setRatio('1.00'))
            ->setSalePrice('10.00');
        $productRepository->method('findOneBy')->willReturn($product);

        $productStockRepository->method('findByProductAndWarehouse')->willReturn(null);

        $paymentMethod = (new PaymentMethod())->setName('Efectivo');
        $this->setEntityId($paymentMethod, 'id', 1);
        $paymentMethodRepository->method('find')->with(1)->willReturn($paymentMethod);

        $connection->method('fetchFirstColumn')->willReturn([]);

        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof SaleRecord) {
                $this->setEntityId($entity, 'id', 'sale-e2e-1');
            }
        });

        $service = new E2eTestSeedService(
            $entityManager,
            $connection,
            $passwordHasher,
            $initialSetupService,
            $companyRepository,
            $roleRepository,
            $userRepository,
            $branchRepository,
            $warehouseRepository,
            $customerRepository,
            $productRepository,
            $productStockRepository,
            $categoryRepository,
            $unitRepository,
            $voucherTypeRepository,
            $voucherSeriesRepository,
            $paymentMethodRepository,
            $cashRegisterRepository,
        );

        $seed = $service->seed();

        self::assertSame('ci_e2e_user', $seed['username']);
        self::assertNotSame('', $seed['password']);
        self::assertSame('sale-e2e-1', $seed['saleId']);
    }

    public function testResetRemovesRefreshTokensAndE2eUserWhenPresent(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $initialSetupService = $this->createMock(InitialSetupService::class);
        $companyRepository = $this->createMock(CompanyRepository::class);
        $roleRepository = $this->createMock(RoleRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $branchRepository = $this->createMock(BranchRepository::class);
        $warehouseRepository = $this->createMock(WarehouseRepository::class);
        $customerRepository = $this->createMock(CustomerRepository::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $productStockRepository = $this->createMock(ProductStockRepository::class);
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $unitRepository = $this->createMock(UnitOfMeasureRepository::class);
        $voucherTypeRepository = $this->createMock(VoucherTypeRepository::class);
        $voucherSeriesRepository = $this->createMock(VoucherSeriesRepository::class);
        $paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $cashRegisterRepository = $this->createMock(CashRegisterRepository::class);

        $user = (new User())->setUsername('ci_e2e_user');
        $this->setEntityId($user, 'id', 9);

        $branch = (new Branch())->setName('CI E2E Branch');
        $cashRegister = (new CashRegister())->setName('Caja CI E2E')->setBranch($branch);
        $this->setEntityId($cashRegister, 'id', 1);

        $connection->method('fetchFirstColumn')->willReturn([]);
        $executeCalls = [];
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$executeCalls): int {
                $executeCalls[] = [$sql, $params];

                return 1;
            });

        $branchRepository->method('findOneBy')->with(['name' => 'CI E2E Branch'])->willReturn($branch);
        $cashRegisterRepository->method('findOneBy')->with(['branch' => $branch])->willReturn($cashRegister);
        $userRepository->method('findOneBy')->with(['username' => 'ci_e2e_user'])->willReturn($user);

        $entityManager->expects($this->once())->method('remove')->with($user);
        $entityManager->expects($this->once())->method('flush');

        $service = new E2eTestSeedService(
            $entityManager,
            $connection,
            $passwordHasher,
            $initialSetupService,
            $companyRepository,
            $roleRepository,
            $userRepository,
            $branchRepository,
            $warehouseRepository,
            $customerRepository,
            $productRepository,
            $productStockRepository,
            $categoryRepository,
            $unitRepository,
            $voucherTypeRepository,
            $voucherSeriesRepository,
            $paymentMethodRepository,
            $cashRegisterRepository,
        );

        $service->reset();

        self::assertSame([
            ['DELETE FROM cash_sessions WHERE cash_register_id = :register_id', ['register_id' => 1]],
            ['DELETE FROM refresh_token WHERE user_id = :user_id', ['user_id' => 9]],
        ], $executeCalls);
    }

    private function setEntityId(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($entity);
        $prop = $reflection->getProperty($property);
        $prop->setValue($entity, $value);
    }
}
