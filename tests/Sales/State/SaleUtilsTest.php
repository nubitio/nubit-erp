<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Core\Exception\ServiceException;
use App\Efact\Service\SettingsService;
use App\Catalog\Entity\PaymentMethod;
use App\Sales\Entity\SaleRecord;
use App\Inventory\Entity\Warehouse;
use App\Sales\Domain\DocType;
use App\Sales\State\SaleUtils;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaleUtils::class)]
class SaleUtilsTest extends TestCase
{
    private Connection&MockObject $connection;
    private SettingsService&MockObject $settings;
    private SaleUtils $utils;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->settings = $this->createMock(SettingsService::class);

        $this->utils = new SaleUtils(
            $this->connection,
            $this->settings,
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a Sale with a PaymentMethod and a Warehouse, ready for fillSaleValues().
     */
    private function buildSaleForFill(int $paymentMethodId, ?int $cajaId = null): SaleRecord
    {
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-uuid');

        $paymentMethod = $this->createMock(PaymentMethod::class);
        $paymentMethod->method('getId')->willReturn($paymentMethodId);

        $sale = new SaleRecord();
        $sale->setAlmacen($warehouse);
        $sale->setTipoPago($paymentMethod);
        $sale->setTotal('100.00');
        $sale->setSubtotal('100.00');
        if ($cajaId !== null) {
            $sale->setCajaId($cajaId);
        }

        return $sale;
    }

    // ── getSaleSeries() ───────────────────────────────────────────────────────

    public function testGetSaleSeriesReturnsSeriesString(): void
    {
        $this->connection->method('fetchOne')->willReturn('F001');

        $result = $this->utils->getSaleSeries('01', 'wh-uuid');

        $this->assertSame('F001', $result);
    }

    public function testGetSaleSeriesThrowsServiceExceptionWhenNotFound(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/No se encontró la serie/');

        $this->utils->getSaleSeries('01', 'wh-uuid');
    }

    // ── getSaleNumber() ───────────────────────────────────────────────────────

    public function testGetSaleNumberReturnsNumberString(): void
    {
        $this->connection->method('executeStatement')->willReturn(1);
        $this->connection->method('fetchOne')->willReturn('42');
        $this->connection->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $result = $this->utils->getSaleNumber('F001');

        $this->assertSame('42', $result);
    }

    public function testGetSaleNumberThrowsServiceExceptionWhenFetchOneReturnsFalse(): void
    {
        $this->connection->method('beginTransaction');
        $this->connection->method('executeStatement')->willReturn(1);
        $this->connection->method('fetchOne')->willReturn(false);
        $this->connection->method('commit');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/No se encontró el número/');

        $this->utils->getSaleNumber('F001');
    }

    public function testGetSaleNumberRollsBackAndThrowsOnException(): void
    {
        $this->connection->method('beginTransaction');
        $this->connection->method('executeStatement')
            ->willThrowException(new \RuntimeException('Lock timeout'));
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No se pudo generar el número de comprobante.');

        $this->utils->getSaleNumber('F001');
    }

    // ── fillSaleValues() ─────────────────────────────────────────────────────

    public function testFillSaleValuesSetsFormaPagoCreditWhenPaymentMethodIdIs2(): void
    {
        $sale = $this->buildSaleForFill(2, 5);

        // Stub getSaleSeries and getSaleNumber via connection
        $fetchOneCallCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$fetchOneCallCount): string {
                ++$fetchOneCallCount;
                return $fetchOneCallCount === 1 ? 'F001' : '1';
            });
        $this->connection->method('executeStatement')->willReturn(1);
        $this->connection->method('beginTransaction');
        $this->connection->method('commit');
        $this->settings->method('get')->willReturn('18');

        $result = $this->utils->fillSaleValues($sale, DocType::NOTA_VENTA);

        $this->assertSame(2, $result->getFormaPago());
    }

    public function testFillSaleValuesSetsFormaPagoCashWhenPaymentMethodIdIsNot2(): void
    {
        $sale = $this->buildSaleForFill(1, 5);

        $fetchOneCallCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$fetchOneCallCount): string {
                ++$fetchOneCallCount;
                return $fetchOneCallCount === 1 ? 'B001' : '5';
            });
        $this->connection->method('executeStatement')->willReturn(1);
        $this->connection->method('beginTransaction');
        $this->connection->method('commit');
        $this->settings->method('get')->willReturn('18');

        $result = $this->utils->fillSaleValues($sale, DocType::BOLETA);

        $this->assertSame(1, $result->getFormaPago());
    }
}
