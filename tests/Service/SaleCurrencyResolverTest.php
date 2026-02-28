<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\Sale;
use App\Service\SaleCurrencyResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class SaleCurrencyResolverTest extends TestCase
{
    public function testResolveForSaleIdFallsBackToLegacyMovimientoId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'legacy_movimiento_id')),
                ['id' => 'legacy-123']
            )
            ->willReturn([
                'currency' => 'USD',
                'exchange_rate' => '3.9',
            ]);

        $settings = $this->createStub(SettingsService::class);
        $settings->method('get')->willReturnMap([
            [Constants::SALES_DEFAULT_CURRENCY, 'PEN', 'PEN'],
            [Constants::SALES_DEFAULT_USD_EXCHANGE_RATE, '3.800000', '3.800000'],
        ]);

        $resolver = new SaleCurrencyResolver($connection, $settings);
        $result = $resolver->resolveForSaleId('legacy-123');

        self::assertSame('USD', $result['currency']);
        self::assertSame('3.900000', $result['exchange_rate']);
    }

    public function testResolveForWriteUsesDefaultsWhenSnapshotMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $settings = $this->createStub(SettingsService::class);
        $settings->method('get')->willReturnMap([
            [Constants::SALES_DEFAULT_CURRENCY, 'PEN', 'PEN'],
            [Constants::SALES_DEFAULT_USD_EXCHANGE_RATE, '3.800000', '3.800000'],
        ]);

        $sale = (new Sale())->setId('sale-1');

        $resolver = new SaleCurrencyResolver($connection, $settings);
        $result = $resolver->resolveForWrite($sale);

        self::assertSame('PEN', $result['currency']);
        self::assertSame('1.000000', $result['exchange_rate']);
    }
}
