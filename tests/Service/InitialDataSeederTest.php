<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\Repository\CashMovementCategoryRepository;
use App\Catalog\Repository\CategoryRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Entity\CashMovementCategory;
use App\Catalog\Entity\Category;
use App\Contacts\Entity\Customer;
use App\Inventory\Entity\LogisticOperation;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Inventory\Repository\LogisticOperationRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Service\InitialDataSeeder;
use App\Service\MenuSeedService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class InitialDataSeederTest extends TestCase
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

    public function testSeedRunsReferenceDataAndMenuSeedWhenEntitiesAlreadyExist(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $menuSeedService = $this->createMock(MenuSeedService::class);
        $menuSeedService->expects(self::once())->method('seed');

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

        $service = new InitialDataSeeder(
            $entityManager,
            $settings,
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

    private function newService(SettingsService $settings): InitialDataSeeder
    {
        return new InitialDataSeeder(
            $this->createMock(EntityManagerInterface::class),
            $settings,
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
