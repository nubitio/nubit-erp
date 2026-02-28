<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Repository\CashMovementCategoryRepository;
use App\Repository\CategoryRepository;
use App\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\SunatCatalogRepository;
use App\Repository\UnitOfMeasureRepository;
use App\Repository\VoucherTypeRepository;
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
        self::assertSame('true', $captured[Constants::SALES_V2_DUAL_WRITE_STRICT]['value'] ?? null);
        self::assertSame('v2_authoritative', $captured[Constants::PURCHASES_V2_MODE]['value'] ?? null);
        self::assertSame('true', $captured[Constants::PURCHASES_V2_DUAL_WRITE_STRICT]['value'] ?? null);
        self::assertArrayNotHasKey('app_option_check_stock', $captured);
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
