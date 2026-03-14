<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Setup\SetupStatusDto;
use App\Entity\Branch;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\PaymentMethod;
use App\Entity\Product;
use App\Entity\Role;
use App\Entity\SaleRecord;
use App\Entity\UnitOfMeasure;
use App\Entity\User;
use App\Entity\VoucherType;
use App\Entity\VoucherSeries;
use App\Entity\Warehouse;
use App\Repository\BranchRepository;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\CustomerRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\ProductRepository;
use App\Repository\RoleRepository;
use App\Repository\UnitOfMeasureRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherSeriesRepository;
use App\Repository\VoucherTypeRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\WarehouseRepository;
use App\Service\E2eTestSeedService;
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
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $unitRepository = $this->createMock(UnitOfMeasureRepository::class);
        $voucherTypeRepository = $this->createMock(VoucherTypeRepository::class);
        $voucherSeriesRepository = $this->createMock(VoucherSeriesRepository::class);
        $paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $cashRegisterRepository = $this->createMock(CashRegisterRepository::class);

        $user = (new User())->setUsername('ci_e2e_user');
        $this->setEntityId($user, 'id', 9);

        $connection->method('fetchFirstColumn')->willReturn([]);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM refresh_token WHERE user_id = :user_id',
                ['user_id' => 9]
            );

        $userRepository->method('findOneBy')->willReturn($user);

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
            $categoryRepository,
            $unitRepository,
            $voucherTypeRepository,
            $voucherSeriesRepository,
            $paymentMethodRepository,
            $cashRegisterRepository,
        );

        $service->reset();
    }

    private function setEntityId(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($entity);
        $prop = $reflection->getProperty($property);
        $prop->setValue($entity, $value);
    }
}
