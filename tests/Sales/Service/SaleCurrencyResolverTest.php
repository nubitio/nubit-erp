<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Catalog\Entity\ExchangeRate;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleCurrencyResolver;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Service\TenantSettingsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class SaleCurrencyResolverTest extends TestCase
{
    private function makeResolver(
        Connection $connection,
        string $defaultCurrency = 'PEN',
        ?string $usdRate = null,
    ): SaleCurrencyResolver {
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getDefaultCurrency')->willReturn(DefaultCurrency::from($defaultCurrency));

        $exchangeRateRepo = $this->createStub(ExchangeRateRepository::class);
        if ($usdRate !== null) {
            $rate = new ExchangeRate('USD', $usdRate, ExchangeRateSource::BCRP_AUTO);
            $exchangeRateRepo->method('findByCurrency')->willReturn($rate);
        } else {
            $exchangeRateRepo->method('findByCurrency')->willReturn(null);
        }

        return new SaleCurrencyResolver($connection, $tenantSettings, $exchangeRateRepo);
    }

    public function testResolveForSaleIdLooksUpSalesById(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->callback(static fn(string $sql): bool => str_contains($sql, 'WHERE s.id = :id')), [
                'id' => 'sale-123',
            ])
            ->willReturn([
                'currency' => 'USD',
                'exchange_rate' => '3.9',
            ]);

        $resolver = $this->makeResolver($connection);
        $result = $resolver->resolveForSaleId('sale-123');

        self::assertSame('USD', $result['currency']);
        self::assertSame('3.900000', $result['exchange_rate']);
    }

    public function testResolveForWriteUsesDefaultsWhenSnapshotMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn(false);

        $sale = new SaleRecord()->setId('sale-1');

        $resolver = $this->makeResolver($connection, 'PEN');
        $result = $resolver->resolveForWrite($sale);

        self::assertSame('PEN', $result['currency']);
        self::assertSame('1.000000', $result['exchange_rate']);
    }

    public function testResolveForSaleIdUsesDefaultsWhenSnapshotMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->anything(), ['id' => 'missing-sale'])
            ->willReturn(false);

        $resolver = $this->makeResolver($connection, 'PEN');
        $result = $resolver->resolveForSaleId('missing-sale');

        self::assertSame('PEN', $result['currency']);
        self::assertSame('1.000000', $result['exchange_rate']);
    }

    public function testResolveForSaleIdUsesConfiguredUsdFallbackWhenSnapshotRateInvalid(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'currency' => 'USD',
                'exchange_rate' => '0',
            ]);

        $resolver = $this->makeResolver($connection, 'PEN', '3.55');
        $result = $resolver->resolveForSaleId('sale-2');

        self::assertSame('USD', $result['currency']);
        self::assertSame('3.550000', $result['exchange_rate']);
    }
}
