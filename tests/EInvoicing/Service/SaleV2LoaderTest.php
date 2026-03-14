<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Service;

use App\Core\Service\SettingsService;
use App\EInvoicing\Service\SaleV2Loader;
use App\Entity\Customer;
use App\Entity\SaleRecord;
use App\Entity\Warehouse;
use App\Sale\Domain\SaleStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
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

        self::assertInstanceOf(SaleRecord::class, $sale);
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

        self::assertInstanceOf(SaleRecord::class, $sale);
        self::assertSame('VOIDED', $sale->getStatus());
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

        self::assertInstanceOf(SaleRecord::class, $sale);
        self::assertSame(SaleStatus::ACTIVE->value, $sale->getEstado());
        self::assertNotSame(SaleStatus::VOIDED->value, $sale->getEstado());
    }

    public function testSerieAndNumeroAreSetWhenPresent(): void
    {
        $row = [
            'id' => 'sale-series',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'tax_total' => '0.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => 'F001',
            'numero' => '42',
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-series');

        self::assertInstanceOf(SaleRecord::class, $sale);
        self::assertSame('F001', $sale->getSerie());
        self::assertSame('42', $sale->getNumero());
    }

    public function testSerieAndNumeroAreNotSetWhenEmpty(): void
    {
        $row = [
            'id' => 'sale-noseries',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'tax_total' => '0.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => '',
            'numero' => '',
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-noseries');

        self::assertNull($sale?->getSerie());
        self::assertNull($sale?->getNumero());
    }

    public function testCustomerIsLoadedWhenCustomerIdIsPresent(): void
    {
        $row = [
            'id' => 'sale-customer',
            'legacy_movimiento_id' => null,
            'customer_id' => '15',
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'tax_total' => '0.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        /** @var Customer&MockObject $customer */
        $customer = $this->createMock(Customer::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getReference')->with(Customer::class, 15)->willReturn($customer);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-customer');

        self::assertSame($customer, $sale?->getCliente());
    }

    public function testWarehouseIsLoadedWhenWarehouseIdIsPresent(): void
    {
        $row = [
            'id' => 'sale-warehouse',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => '5',
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'tax_total' => '0.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        /** @var Warehouse&MockObject $warehouse */
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getBranch')->willReturn(null);

        /** @var EntityRepository<Warehouse>&MockObject $warehouseRepo */
        $warehouseRepo = $this->createMock(EntityRepository::class);
        $warehouseRepo->method('find')->with('5')->willReturn($warehouse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($warehouseRepo);

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-warehouse');

        self::assertSame($warehouse, $sale?->getAlmacen());
    }

    public function testResolveIgvRateFallsBackToSettingsWhenTaxIsZero(): void
    {
        $row = [
            'id' => 'sale-taxzero',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'tax_total' => '0.00',  // tax_total = 0 → fall back to settings
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturn('18');

        $loader = new SaleV2Loader($connection, $em, $settings);
        $sale   = $loader->loadAsSale('sale-taxzero');

        self::assertSame('18', $sale?->getIgv());
    }

    public function testResolveIgvRateFallsBackToSettingsWhenSubtotalIsZero(): void
    {
        $row = [
            'id' => 'sale-subzero',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '0.00',  // subtotal = 0 → fall back to settings
            'discount_total' => '0.00',
            'grand_total' => '0.00',
            'tax_total' => '18.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturn('18');

        $loader = new SaleV2Loader($connection, $em, $settings);
        $sale   = $loader->loadAsSale('sale-subzero');

        self::assertSame('18', $sale?->getIgv());
    }

    public function testResolveIgvRateDerivesRateFromTaxAndSubtotal(): void
    {
        $row = [
            'id' => 'sale-igv-derive',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '118.00',
            'tax_total' => '18.00',  // 18/100 * 100 = 18%
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturn('10'); // not used

        $loader = new SaleV2Loader($connection, $em, $settings);
        $sale   = $loader->loadAsSale('sale-igv-derive');

        self::assertSame('18', $sale?->getIgv());
    }

    public function testSaleItemsAreLoadedAndAddedToSale(): void
    {
        $saleRow = [
            'id' => 'sale-items',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '118.00',
            'tax_total' => '18.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $itemRow = [
            'sale_id' => 'sale-items',
            'product_id' => '42',
            'qty' => '3.0',
            'unit_price' => '50.00',
            'net_amount' => '150.00',
            'discount_amount' => '0.00',
            'line_no' => 1,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($saleRow);
        $connection->method('fetchAllAssociative')->willReturn([$itemRow]);

        $product = $this->createMock(\App\Entity\Product::class);

        /** @var EntityRepository<\App\Entity\Product>&MockObject $productRepo */
        $productRepo = $this->createMock(EntityRepository::class);
        $productRepo->method('find')->with('42')->willReturn($product);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($productRepo);

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-items');

        self::assertNotNull($sale);
        self::assertCount(1, $sale->getItems());
    }

    public function testSaleItemIsSkippedWhenProductIdIsNull(): void
    {
        $saleRow = [
            'id' => 'sale-noproductid',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '118.00',
            'tax_total' => '18.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $itemRow = [
            'sale_id' => 'sale-noproductid',
            'product_id' => null,  // null product_id → item skipped
            'qty' => '1.0',
            'unit_price' => '10.00',
            'net_amount' => '10.00',
            'discount_amount' => '0.00',
            'line_no' => 1,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($saleRow);
        $connection->method('fetchAllAssociative')->willReturn([$itemRow]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-noproductid');

        self::assertNotNull($sale);
        self::assertCount(0, $sale->getItems());
    }

    public function testSaleItemIsSkippedWhenProductNotFound(): void
    {
        $saleRow = [
            'id' => 'sale-productnotfound',
            'legacy_movimiento_id' => null,
            'customer_id' => null,
            'warehouse_id' => null,
            'branch_id' => null,
            'seller_user_id' => null,
            'status' => 'COMPLETED',
            'currency' => 'PEN',
            'exchange_rate' => '1.000000',
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'grand_total' => '118.00',
            'tax_total' => '18.00',
            'issued_at' => null,
            'created_at' => null,
            'serie' => null,
            'numero' => null,
        ];

        $itemRow = [
            'sale_id' => 'sale-productnotfound',
            'product_id' => '99',
            'qty' => '1.0',
            'unit_price' => '10.00',
            'net_amount' => '10.00',
            'discount_amount' => '0.00',
            'line_no' => 1,
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($saleRow);
        $connection->method('fetchAllAssociative')->willReturn([$itemRow]);

        /** @var EntityRepository<\App\Entity\Product>&MockObject $productRepo */
        $productRepo = $this->createMock(EntityRepository::class);
        $productRepo->method('find')->with('99')->willReturn(null); // product not found

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($productRepo);

        $loader = $this->makeLoader($connection, $em);
        $sale   = $loader->loadAsSale('sale-productnotfound');

        self::assertNotNull($sale);
        self::assertCount(0, $sale->getItems());
    }
}
