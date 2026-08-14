<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\AccountsPayable\Entity\ExpenseCategory;
use App\AccountsPayable\Repository\ExpenseCategoryRepository;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Repository\CashMovementCategoryRepository;
use App\CashRegister\Repository\CashRegisterRepository;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Catalog\Repository\VoucherSeriesRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Entity\Role;
use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Inventory\Entity\LogisticOperation;
use App\Inventory\Repository\LogisticOperationRepository;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use App\Reporting\Service\TimeDimensionSeeder;
use App\Settings\Service\MenuSeedService;
use App\Settings\Service\SunatStateService;
use App\Settings\Service\TenantSettingsService;
use App\Settings\Service\UbigeoImportService;
use App\Setup\InitialSetupService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class InitialSetupServiceTest extends TestCase
{
    public function testSeedSettingsUsesAuthoritativeDefaults(): void
    {
        $tenantSettings = $this->createMock(TenantSettingsService::class);
        $sunatState = $this->createMock(SunatStateService::class);

        // TenantSettings entity is created via getOrCreate() → save()
        $tenantSettings->expects($this->atLeastOnce())->method('get');
        $tenantSettings->expects($this->once())->method('save');
        $sunatState->expects($this->atLeastOnce())->method('get');
        $sunatState->expects($this->once())->method('save');

        $service = $this->newService($tenantSettings, $sunatState);
        $this->invokePrivate($service, 'seedSettings');
    }

    public function testSeedSettingsIsSafeWhenSettingsStorageFails(): void
    {
        $this->expectNotToPerformAssertions();

        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('get')->willThrowException(new \RuntimeException('boom'));

        $service = $this->newService($tenantSettings);
        $this->invokePrivate($service, 'seedSettings');
    }

    public function testStatusReflectsRepositoryCounts(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('count')->willReturn(1);
        $companyRepository = $this->createStub(CompanyRepository::class);
        $companyRepository->method('count')->willReturn(1);
        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('count')->willReturn(1);

        $service = $this->newService(
            userRepository: $userRepository,
            companyRepository: $companyRepository,
            branchRepository: $branchRepository,
        );

        $status = $service->status();
        self::assertTrue($status->configured);
        self::assertSame(1, $status->userCount);
        self::assertSame(1, $status->companyCount);
        self::assertSame(1, $status->branchCount);
    }

    public function testEnsureDefaultRolesCreatesAndReturnsSuperAdminRole(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(3))->method('executeStatement');

        $superAdmin = new Role()->setName('Super Administrador');
        $roleRepository = $this->createMock(RoleRepository::class);
        $roleRepository->method('find')->with(1)->willReturn($superAdmin);

        $service = $this->newService(connection: $connection, roleRepository: $roleRepository);

        $result = $this->invokePrivate($service, 'ensureDefaultRoles');
        self::assertSame($superAdmin, $result);
    }

    public function testEnsureDefaultRolesThrowsWhenRoleCannotBePrepared(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(3))->method('executeStatement');

        $roleRepository = $this->createMock(RoleRepository::class);
        $roleRepository->method('find')->willReturn(null);
        $roleRepository->method('findOneBy')->willReturn(null);

        $service = $this->newService(connection: $connection, roleRepository: $roleRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo preparar el rol de super administrador.');
        $this->invokePrivate($service, 'ensureDefaultRoles');
    }

    public function testAssignFullMenuToRoleInsertsAllRelations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([10, 11, 12]);
        $connection->expects(self::exactly(3))->method('executeStatement');

        $service = $this->newService(connection: $connection);
        $this->invokePrivate($service, 'assignFullMenuToRole', [3]);
    }

    public function testSeedReferenceDataSkipsPersistWhenAllRowsAlreadyExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $logisticRepo = $this->createStub(LogisticOperationRepository::class);
        $logisticRepo->method('findOneBy')->willReturn(new LogisticOperation());
        $paymentRepo = $this->createStub(PaymentMethodRepository::class);
        $paymentRepo->method('findOneBy')->willReturn(new PaymentMethod());
        $cashRepo = $this->createStub(CashMovementCategoryRepository::class);
        $cashRepo->method('findOneBy')->willReturn(new CashMovementCategory());
        $expenseCategoryRepo = $this->createStub(ExpenseCategoryRepository::class);
        $expenseCategoryRepo->method('findOneBy')->willReturn(new ExpenseCategory());
        $voucherRepo = $this->createStub(VoucherTypeRepository::class);
        $voucherRepo->method('find')->willReturn(new VoucherType());
        $unitRepo = $this->createStub(UnitOfMeasureRepository::class);
        $unitRepo->method('findOneBy')->willReturn(new UnitOfMeasure());
        $categoryRepo = $this->createStub(CategoryRepository::class);
        $categoryRepo->method('findOneBy')->willReturn(new Category());
        $customerRepo = $this->createStub(CustomerRepository::class);
        $customerRepo->method('findOneBy')->willReturn(new Customer());
        $sunatRepo = $this->createStub(SunatCatalogRepository::class);
        $sunatRepo->method('findOneBy')->willReturn(new SunatCatalog());

        $service = $this->newService(
            entityManager: $entityManager,
            logisticOperationRepository: $logisticRepo,
            paymentMethodRepository: $paymentRepo,
            cashMovementCategoryRepository: $cashRepo,
            expenseCategoryRepository: $expenseCategoryRepo,
            voucherTypeRepository: $voucherRepo,
            unitOfMeasureRepository: $unitRepo,
            categoryRepository: $categoryRepo,
            customerRepository: $customerRepo,
            sunatCatalogRepository: $sunatRepo,
        );

        $this->invokePrivate($service, 'seedReferenceData');
    }

    private function newService(
        ?TenantSettingsService $tenantSettingsService = null,
        ?SunatStateService $sunatStateService = null,
        ?EntityManagerInterface $entityManager = null,
        ?UserRepository $userRepository = null,
        ?CompanyRepository $companyRepository = null,
        ?BranchRepository $branchRepository = null,
        ?RoleRepository $roleRepository = null,
        ?LogisticOperationRepository $logisticOperationRepository = null,
        ?PaymentMethodRepository $paymentMethodRepository = null,
        ?CashMovementCategoryRepository $cashMovementCategoryRepository = null,
        ?CashRegisterRepository $cashRegisterRepository = null,
        ?ExpenseCategoryRepository $expenseCategoryRepository = null,
        ?VoucherTypeRepository $voucherTypeRepository = null,
        ?VoucherSeriesRepository $voucherSeriesRepository = null,
        ?UnitOfMeasureRepository $unitOfMeasureRepository = null,
        ?CategoryRepository $categoryRepository = null,
        ?CustomerRepository $customerRepository = null,
        ?SunatCatalogRepository $sunatCatalogRepository = null,
        ?Connection $connection = null,
        ?UbigeoImportService $ubigeoImportService = null,
        ?TimeDimensionSeeder $timeDimensionSeeder = null,
        ?MenuSeedService $menuSeedService = null,
    ): InitialSetupService {
        return new InitialSetupService(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $userRepository ?? $this->createMock(UserRepository::class),
            $companyRepository ?? $this->createMock(CompanyRepository::class),
            $branchRepository ?? $this->createMock(BranchRepository::class),
            $roleRepository ?? $this->createMock(RoleRepository::class),
            $logisticOperationRepository ?? $this->createMock(LogisticOperationRepository::class),
            $paymentMethodRepository ?? $this->createMock(PaymentMethodRepository::class),
            $cashMovementCategoryRepository ?? $this->createMock(CashMovementCategoryRepository::class),
            $cashRegisterRepository ?? $this->createMock(CashRegisterRepository::class),
            $expenseCategoryRepository ?? $this->createMock(ExpenseCategoryRepository::class),
            $voucherTypeRepository ?? $this->createMock(VoucherTypeRepository::class),
            $voucherSeriesRepository ?? $this->createMock(VoucherSeriesRepository::class),
            $unitOfMeasureRepository ?? $this->createMock(UnitOfMeasureRepository::class),
            $categoryRepository ?? $this->createMock(CategoryRepository::class),
            $customerRepository ?? $this->createMock(CustomerRepository::class),
            $sunatCatalogRepository ?? $this->createMock(SunatCatalogRepository::class),
            $connection ?? $this->createMock(Connection::class),
            $tenantSettingsService ?? $this->createMock(TenantSettingsService::class),
            $sunatStateService ?? $this->createMock(SunatStateService::class),
            $ubigeoImportService ?? $this->createMock(UbigeoImportService::class),
            $timeDimensionSeeder ?? $this->createMock(TimeDimensionSeeder::class),
            $menuSeedService ?? $this->createMock(MenuSeedService::class),
        );
    }

    /**
     * @param list<mixed> $args
     */
    private function invokePrivate(object $service, string $methodName, array $args = []): mixed
    {
        $method = new \ReflectionMethod($service, $methodName);

        return $method->invokeArgs($service, $args);
    }
}
