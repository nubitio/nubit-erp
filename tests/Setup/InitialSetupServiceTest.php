<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Repository\BranchRepository;
use App\Repository\CashMovementCategoryRepository;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\RoleRepository;
use App\Repository\SunatCatalogRepository;
use App\Repository\UnitOfMeasureRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherSeriesRepository;
use App\Repository\VoucherTypeRepository;
use App\Service\MenuSeedService;
use App\Service\TimeDimensionSeeder;
use App\Service\UbigeoImportService;
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
        self::assertSame('true', $captured[Constants::SALES_V2_DUAL_WRITE_STRICT]['value'] ?? null);
        self::assertSame('v2_authoritative', $captured[Constants::PURCHASES_V2_MODE]['value'] ?? null);
        self::assertSame('true', $captured[Constants::PURCHASES_V2_DUAL_WRITE_STRICT]['value'] ?? null);
        self::assertArrayNotHasKey('sales_v2_disable_legacy_cash_movement', $captured);
        self::assertArrayNotHasKey('sales_v2_disable_legacy_stock_projection', $captured);
    }

    public function testSeedSettingsIsSafeWhenSettingsStorageFails(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('set')->willThrowException(new \RuntimeException('boom'));

        $service = $this->newService($settings);
        $this->invokePrivate($service, 'seedSettings');

        self::assertTrue(true);
    }

    private function newService(SettingsService $settings): InitialSetupService
    {
        return new InitialSetupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(UserRepository::class),
            $this->createMock(CompanyRepository::class),
            $this->createMock(BranchRepository::class),
            $this->createMock(RoleRepository::class),
            $this->createMock(LogisticOperationRepository::class),
            $this->createMock(PaymentMethodRepository::class),
            $this->createMock(CashMovementCategoryRepository::class),
            $this->createMock(VoucherTypeRepository::class),
            $this->createMock(VoucherSeriesRepository::class),
            $this->createMock(UnitOfMeasureRepository::class),
            $this->createMock(CategoryRepository::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(SunatCatalogRepository::class),
            $this->createMock(Connection::class),
            $settings,
            $this->createMock(UbigeoImportService::class),
            $this->createMock(TimeDimensionSeeder::class),
            $this->createMock(MenuSeedService::class),
        );
    }

    private function invokePrivate(object $service, string $methodName): void
    {
        $method = new \ReflectionMethod($service, $methodName);
        $method->setAccessible(true);
        $method->invoke($service);
    }
}

