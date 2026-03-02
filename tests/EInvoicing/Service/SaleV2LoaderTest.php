<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Service;

use App\Core\Service\SettingsService;
use App\EInvoicing\Service\SaleV2Loader;
use App\Entity\Sale;
use App\Sale\Domain\SaleStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SaleV2LoaderTest extends TestCase
{
    private function makeLoader(Connection $connection, EntityManagerInterface $em): SaleV2Loader
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturn('18');

        return new SaleV2Loader($connection, $em, $settings);
    }

    public function testReturnsNullWhenSaleNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);

        $loader = $this->makeLoader($connection, $em);

        self::assertNull($loader->loadAsSale('non-existent-id'));
    }

    public function testReturnsSaleEntityWithCorrectFields(): void
    {
        $row = [
            'id' => 'abc-123',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => '7',
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'tax_total' => '0.00',
            'issued_at' => '2025-01-15 10:00:00',
            'created_at' => '2025-01-15 09:00:00',
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale = $loader->loadAsSale('abc-123');

        self::assertInstanceOf(Sale::class, $sale);
        self::assertSame('abc-123', $sale->getId());
        self::assertSame('PEN', $sale->getCurrency());
        self::assertSame(7, $sale->getCreatedBy());
        self::assertSame(SaleStatus::ACTIVE->value, $sale->getEstado());
    }

    public function testMapsVoidedStatusCorrectly(): void
    {
        $row = [
            'id' => 'void-sale',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'VOIDED',
            'currency' => 'USD',
            'exchange_rate' => '3.800000',
            'subtotal' => '50.00',
            'discount_total' => '0.00',
            'grand_total' => '50.00',
            'tax_total' => '0.00',
            'issued_at' => null,
            'created_at' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale = $loader->loadAsSale('void-sale');

        self::assertInstanceOf(Sale::class, $sale);
        self::assertSame(SaleStatus::VOIDED->value, $sale->getEstado());
    }

    public function testActiveSaleStatusMapsToActiveValue(): void
    {
        $row = [
            'id' => 'active-sale',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '200.00',
            'discount_total' => '0.00',
            'grand_total' => '200.00',
            'tax_total' => '0.00',
            'issued_at' => null,
            'created_at' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale = $loader->loadAsSale('active-sale');

        self::assertInstanceOf(Sale::class, $sale);
        self::assertSame(SaleStatus::ACTIVE->value, $sale->getEstado());
        self::assertNotSame(SaleStatus::VOIDED->value, $sale->getEstado());
    }
}
