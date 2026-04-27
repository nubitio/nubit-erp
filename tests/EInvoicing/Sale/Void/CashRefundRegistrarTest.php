<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Sale\Void;

use App\Entity\CashMovementCategory;
use App\Catalog\Entity\PaymentMethod;
use App\Sales\Entity\SaleRecord;
use App\EInvoicing\Sale\Void\CashRefundRegistrar;
use App\State\Sale\CashSessionResolverInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CashRefundRegistrarTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Happy path: cash session found + cash payment → cash_movements INSERT
    // ---------------------------------------------------------------------------

    public function testInsertsRowIntoCashMovementsWhenSessionAndCashPayment(): void
    {
        $sale = $this->makeSale(id: 'sale-001', total: '150.00', serie: 'B001', numero: '100');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: 42);

        // Simulate category lookup returning a row
        $connection->method('fetchAssociative')
            ->with(self::stringContains('cash_movement_category'), ['code' => CashMovementCategory::CUSTOMER_REFUND])
            ->willReturn(['id' => 7]);

        $executed = false;
        $connection->method('executeStatement')
            ->willReturnCallback(static function () use (&$executed): int {
                $executed = true;

                return 1;
            });

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);

        self::assertTrue($executed, 'executeStatement was not called — no row was inserted');
    }

    public function testInsertDescriptionContainsSerieAndNumero(): void
    {
        $sale = $this->makeSale(id: 'sale-desc', total: '200.00', serie: 'F001', numero: '999');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: 5);

        $connection->method('fetchAssociative')->willReturn(['id' => 3]);

        /** @var string|null $capturedSql */
        $capturedSql  = null;
        /** @var array<string, mixed>|null $capturedParams */
        $capturedParams = null;
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedSql, &$capturedParams): int {
                $capturedSql    = $sql;
                $capturedParams = $params;

                return 1;
            });

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);

        self::assertNotNull($capturedParams, 'executeStatement was not called');
        self::assertStringContainsString('F001', $capturedParams['description']);
        self::assertStringContainsString('999', $capturedParams['description']);
    }

    public function testInsertUsesCorrectMovementType(): void
    {
        $sale = $this->makeSale(id: 'sale-mtype', total: '50.00');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: 1);

        $connection->method('fetchAssociative')->willReturn(['id' => 2]);

        $capturedSql = '';
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql = $sql;

                return 1;
            });

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);

        self::assertStringContainsString("'REFUND_OUT'", $capturedSql);
    }

    public function testInsertPassesCorrectAmount(): void
    {
        $sale = $this->makeSale(id: 'sale-amt', total: '75.50');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: 10);

        $connection->method('fetchAssociative')->willReturn(['id' => 4]);

        /** @var array<string, mixed>|null $capturedParams */
        $capturedParams = null;
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): int {
                $capturedParams = $params;

                return 1;
            });

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);

        self::assertNotNull($capturedParams);
        self::assertSame('75.50', $capturedParams['amount']);
    }

    // ---------------------------------------------------------------------------
    // No cash session → graceful skip, no DB interaction
    // ---------------------------------------------------------------------------

    public function testGracefullySkipsWhenNoSessionFound(): void
    {
        $sale = $this->makeSale(id: 'sale-noshift', total: '200.00');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: null);

        $connection->expects(self::never())->method('fetchAssociative');
        $connection->expects(self::never())->method('executeStatement');

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);
    }

    public function testNoExceptionThrownWhenNoSessionFound(): void
    {
        $sale = $this->makeSale(id: 'sale-noshift-ok', total: '100.00');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: null);

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());

        $this->expectNotToPerformAssertions();
        $registrar->register($sale, 42);
    }

    // ---------------------------------------------------------------------------
    // Non-cash payment method → immediate return, no DB interaction at all
    // ---------------------------------------------------------------------------

    public function testDoesNothingWhenSaleHasNoCashPaymentMethod(): void
    {
        $sale = $this->makeSale(id: 'sale-nocash', total: '300.00');
        // No tipo_pago → getTipoPago() returns null → early return

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: null);

        $connection->expects(self::never())->method('fetchAssociative');
        $connection->expects(self::never())->method('executeStatement');
        $resolver->expects(self::never())->method('tryResolveForSale');

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);
    }

    public function testDoesNothingWhenSaleHasNonCashPaymentMethod(): void
    {
        $paymentMethod = new PaymentMethod();
        $prop          = (new \ReflectionClass(PaymentMethod::class))->getProperty('id');
        $prop->setValue($paymentMethod, 2); // credit card

        $sale = $this->makeSale(id: 'sale-card', total: '500.00');
        $sale->setTipoPago($paymentMethod);

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: null);

        $connection->expects(self::never())->method('fetchAssociative');
        $connection->expects(self::never())->method('executeStatement');
        $resolver->expects(self::never())->method('tryResolveForSale');

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);
    }

    // ---------------------------------------------------------------------------
    // Category not found → graceful skip, no INSERT
    // ---------------------------------------------------------------------------

    public function testGracefullySkipsWhenRefundCategoryNotFound(): void
    {
        $sale = $this->makeSale(id: 'sale-nocat', total: '80.00');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: 3);

        $connection->method('fetchAssociative')->willReturn(false); // category missing

        $connection->expects(self::never())->method('executeStatement');

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);
    }

    // ---------------------------------------------------------------------------
    // Idempotency: ON DUPLICATE KEY UPDATE present in SQL
    // ---------------------------------------------------------------------------

    public function testInsertSqlContainsOnDuplicateKeyUpdate(): void
    {
        $sale = $this->makeSale(id: 'sale-idem', total: '30.00');
        $sale->setTipoPago($this->makeCashPaymentMethod());

        $connection = $this->createConnectionMock();
        $resolver   = $this->createResolverMock(sessionId: 9);

        $connection->method('fetchAssociative')->willReturn(['id' => 1]);

        $capturedSql = '';
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
                $capturedSql = $sql;

                return 1;
            });

        $registrar = new CashRefundRegistrar($connection, $resolver, new NullLogger());
        $registrar->register($sale, 1);

        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $capturedSql);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeCashPaymentMethod(): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $prop          = (new \ReflectionClass(PaymentMethod::class))->getProperty('id');
        $prop->setValue($paymentMethod, PaymentMethod::CASH);

        return $paymentMethod;
    }

    private function makeSale(
        string $id,
        string $total = '100.00',
        string $serie = 'B001',
        string $numero = '1',
    ): SaleRecord {
        $sale = new SaleRecord();
        $sale->setId($id);
        $sale->setTotal($total);
        $sale->setSerie($serie);
        $sale->setNumero($numero);

        return $sale;
    }

    /**
     * @return Connection&MockObject
     */
    private function createConnectionMock(): Connection
    {
        return $this->createMock(Connection::class);
    }

    /**
     * @return CashSessionResolverInterface&MockObject
     */
    private function createResolverMock(?int $sessionId): CashSessionResolverInterface&MockObject
    {
        $resolver = $this->createMock(CashSessionResolverInterface::class);
        $resolver->method('tryResolveForSale')->willReturn($sessionId);

        return $resolver;
    }
}
