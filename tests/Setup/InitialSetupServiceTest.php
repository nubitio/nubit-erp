<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\CashRegister\Entity\CashMovementCategory;
use App\Catalog\Entity\Category;
use App\Contacts\Entity\Customer;
use App\Inventory\Entity\LogisticOperation;
use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\Role;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Organization\Repository\BranchRepository;
use App\CashRegister\Repository\CashMovementCategoryRepository;
use App\Catalog\Repository\CategoryRepository;
use App\Organization\Repository\CompanyRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Inventory\Repository\LogisticOperationRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Identity\Repository\RoleRepository;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Identity\Repository\UserRepository;
use App\Catalog\Repository\VoucherSeriesRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Settings\Service\MenuSeedService;
use App\Reporting\Service\TimeDimensionSeeder;
use App\Settings\Service\UbigeoImportService;
use App\Setup\InitialSetupService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InitialSetupServiceTest extends TestCase
{
    public function testSeedSettingsUsesAuthoritativeDefaults(): void
    {
        $captured = [];

        $settings = $this->createMock(SettingsService::class);
        $settings->method('set')->willReturnCallback(
            static function (string $name, string $type, string $value) use (&$captured): void {
                $captured[$name] = ['type' => $type, 'value' => $value];
            }
        );

        $service = $this->newService($settings);
        $this->invokePrivate($service, 'seedSettings');

        self::assertSame('v2_authoritative', $captured[Constants::SALES_V2_MODE]['value'] ?? null);
        self::assertSame('v2_authoritative', $captured[Constants::PURCHASES_V2_MODE]['value'] ?? null);
        self::assertSame('true', $captured[Constants::PURCHASES_V2_DUAL_WRITE_STRICT]['value'] ?? null);
        self::assertArrayNotHasKey('app_option_check_stock', $captured);
        self::assertArrayNotHasKey('sales_v2_disable_legacy_cash_movement', $captured);
        self::assertArrayNotHasKey('sales_v2_disable_legacy_stock_projection', $captured);
    }

    public function testSeedSettingsIsSafeWhenSettingsStorageFails(): void
    {
        $this->expectNotToPerformAssertions();

        $settings = $this->createMock(SettingsService::class);
        $settings->method('set')->willThrowException(new \RuntimeException('boom'));

        $service = $this->newService($settings);
        $this->invokePrivate($service, 'seedSettings');
    }

    public function testStatusReflectsRepositoryCounts(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('count')->willReturn(1);
        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('count')->willReturn(1);
        $branchRepository = $this->createMock(BranchRepository::class);
        $branchRepository->method('count')->willReturn(1);

        $service = $this->newService(
            $this->createMock(SettingsService::class),
            userRepository: $userRepository,
            companyRepository: $companyRepository,
            branchRepository: $branchRepository
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

        $superAdmin = (new Role())->setName('Super Administrador');
        $roleRepository = $this->createMock(RoleRepository::class);
        $roleRepository->method('find')->with(1)->willReturn($superAdmin);

        $service = $this->newService(
            $this->createMock(SettingsService::class),
            connection: $connection,
            roleRepository: $roleRepository
        );

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

        $service = $this->newService(
            $this->createMock(SettingsService::class),
            connection: $connection,
            roleRepository: $roleRepository
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo preparar el rol de super administrador.');
        $this->invokePrivate($service, 'ensureDefaultRoles');
    }

    public function testAssignFullMenuToRoleInsertsAllRelations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([10, 11, 12]);
        $connection->expects(self::exactly(3))->method('executeStatement');

        $service = $this->newService($this->createMock(SettingsService::class), connection: $connection);
        $this->invokePrivate($service, 'assignFullMenuToRole', [3]);
    }

    public function testSeedReferenceDataSkipsPersistWhenAllRowsAlreadyExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $logisticRepo = $this->createMock(LogisticOperationRepository::class);
        $logisticRepo->method('findOneBy')->willReturn(new LogisticOperation());
        $paymentRepo = $this->createMock(PaymentMethodRepository::class);
        $paymentRepo->method('findOneBy')->willReturn(new PaymentMethod());
        $cashRepo = $this->createMock(CashMovementCategoryRepository::class);
        $cashRepo->method('findOneBy')->willReturn(new CashMovementCategory());
        $voucherRepo = $this->createMock(VoucherTypeRepository::class);
        $voucherRepo->method('find')->willReturn(new VoucherType());
        $unitRepo = $this->createMock(UnitOfMeasureRepository::class);
        $unitRepo->method('findOneBy')->willReturn(new UnitOfMeasure());
        $categoryRepo = $this->createMock(CategoryRepository::class);
        $categoryRepo->method('findOneBy')->willReturn(new Category());
        $customerRepo = $this->createMock(CustomerRepository::class);
        $customerRepo->method('findOneBy')->willReturn(new Customer());
        $sunatRepo = $this->createMock(SunatCatalogRepository::class);
        $sunatRepo->method('findOneBy')->willReturn(new SunatCatalog());

        $service = $this->newService(
            $this->createMock(SettingsService::class),
            entityManager: $entityManager,
            logisticOperationRepository: $logisticRepo,
            paymentMethodRepository: $paymentRepo,
            cashMovementCategoryRepository: $cashRepo,
            voucherTypeRepository: $voucherRepo,
            unitOfMeasureRepository: $unitRepo,
            categoryRepository: $categoryRepo,
            customerRepository: $customerRepo,
            sunatCatalogRepository: $sunatRepo
        );

        $this->invokePrivate($service, 'seedReferenceData');
    }

    private function newService(
        SettingsService $settings,
        ?EntityManagerInterface $entityManager = null,
        ?UserRepository $userRepository = null,
        ?CompanyRepository $companyRepository = null,
        ?BranchRepository $branchRepository = null,
        ?RoleRepository $roleRepository = null,
        ?LogisticOperationRepository $logisticOperationRepository = null,
        ?PaymentMethodRepository $paymentMethodRepository = null,
        ?CashMovementCategoryRepository $cashMovementCategoryRepository = null,
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
            $voucherTypeRepository ?? $this->createMock(VoucherTypeRepository::class),
            $voucherSeriesRepository ?? $this->createMock(VoucherSeriesRepository::class),
            $unitOfMeasureRepository ?? $this->createMock(UnitOfMeasureRepository::class),
            $categoryRepository ?? $this->createMock(CategoryRepository::class),
            $customerRepository ?? $this->createMock(CustomerRepository::class),
            $sunatCatalogRepository ?? $this->createMock(SunatCatalogRepository::class),
            $connection ?? $this->createMock(Connection::class),
            $settings,
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
