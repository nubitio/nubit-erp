<?php

declare(strict_types=1);

namespace App\Tests\Setup\Service;

use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Repository\CashMovementCategoryRepository;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Inventory\Entity\LogisticOperation;
use App\Inventory\Repository\LogisticOperationRepository;
use App\Settings\Service\MenuSeedService;
use App\Settings\Service\SunatStateService;
use App\Settings\Service\TenantSettingsService;
use App\Setup\Service\InitialDataSeeder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class InitialDataSeederTest extends TestCase
{
    public function testSeedSettingsUsesAuthoritativeDefaults(): void
    {
        $tenantSettings = $this->createMock(TenantSettingsService::class);
        $sunatState = $this->createMock(SunatStateService::class);
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

    public function testSeedRunsReferenceDataAndMenuSeedWhenEntitiesAlreadyExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $menuSeedService = $this->createMock(MenuSeedService::class);
        $menuSeedService->expects(self::once())->method('seed');

        $logisticRepo = $this->createStub(LogisticOperationRepository::class);
        $logisticRepo->method('findOneBy')->willReturn(new LogisticOperation());
        $paymentRepo = $this->createStub(PaymentMethodRepository::class);
        $paymentRepo->method('findOneBy')->willReturn(new PaymentMethod());
        $cashRepo = $this->createStub(CashMovementCategoryRepository::class);
        $cashRepo->method('findOneBy')->willReturn(new CashMovementCategory());
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

        $service = new InitialDataSeeder(
            $entityManager,
            $this->createMock(TenantSettingsService::class),
            $this->createMock(SunatStateService::class),
            $menuSeedService,
            $logisticRepo,
            $paymentRepo,
            $cashRepo,
            $voucherRepo,
            $unitRepo,
            $categoryRepo,
            $customerRepo,
            $sunatRepo,
        );

        $service->seed();
    }

    private function newService(
        ?TenantSettingsService $tenantSettings = null,
        ?SunatStateService $sunatState = null,
    ): InitialDataSeeder {
        return new InitialDataSeeder(
            $this->createMock(EntityManagerInterface::class),
            $tenantSettings ?? $this->createMock(TenantSettingsService::class),
            $sunatState ?? $this->createMock(SunatStateService::class),
            $this->createMock(MenuSeedService::class),
            $this->createMock(LogisticOperationRepository::class),
            $this->createMock(PaymentMethodRepository::class),
            $this->createMock(CashMovementCategoryRepository::class),
            $this->createMock(VoucherTypeRepository::class),
            $this->createMock(UnitOfMeasureRepository::class),
            $this->createMock(CategoryRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(SunatCatalogRepository::class),
        );
    }

    private function invokePrivate(object $service, string $methodName): void
    {
        $method = new \ReflectionMethod($service, $methodName);
        $method->invoke($service);
    }
}
