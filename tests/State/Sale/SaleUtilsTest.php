<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Core\Application\Session\Security;
use App\Core\Exception\ServiceException;
use App\Core\Service\SettingsService;
use App\Entity\PaymentMethod;
use App\Entity\Sale;
use App\Entity\Warehouse;
use App\Sale\Domain\DocType;
use App\State\Sale\SaleUtils;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaleUtils::class)]
class SaleUtilsTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private Connection&MockObject $connection;
    private SettingsService&MockObject $settings;
    private Security&MockObject $security;
    private SaleUtils $utils;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->settings = $this->createMock(SettingsService::class);
        $this->security = $this->createMock(Security::class);

        $this->utils = new SaleUtils(
            $this->entityManager,
            $this->connection,
            $this->settings,
            $this->security,
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a Sale with a PaymentMethod and a Warehouse, ready for fillSaleValues().
     */
    private function buildSaleForFill(int $paymentMethodId, ?int $cajaId = null): Sale
    {
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('wh-uuid');

        $paymentMethod = $this->createMock(PaymentMethod::class);
        $paymentMethod->method('getId')->willReturn($paymentMethodId);

        $sale = new Sale();
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

    public function testFillSaleValuesDoesNotCallGetCashBoxIdWhenCajaIdAlreadySet(): void
    {
        $sale = $this->buildSaleForFill(1, 99);

        $fetchOneCallCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$fetchOneCallCount): string {
                ++$fetchOneCallCount;
                return $fetchOneCallCount === 1 ? 'B001' : '7';
            });
        $this->connection->method('executeStatement')->willReturn(1);
        $this->connection->method('beginTransaction');
        $this->connection->method('commit');
        $this->settings->method('get')->willReturn('18');

        // Security should NOT be called because cajaId is already set
        $this->security->expects($this->never())->method('connectedUser');

        $result = $this->utils->fillSaleValues($sale);

        $this->assertSame(99, $result->getCajaId());
    }

    public function testFillSaleValuesCallsGetCashBoxIdWhenCajaIdIsNull(): void
    {
        $sale = $this->buildSaleForFill(1); // no cajaId

        $fetchOneCallCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$fetchOneCallCount): string {
                ++$fetchOneCallCount;
                return $fetchOneCallCount === 1 ? 'B001' : '3';
            });
        $this->connection->method('executeStatement')->willReturn(1);
        $this->connection->method('beginTransaction');
        $this->connection->method('commit');
        $this->settings->method('get')->willReturn('18');

        // getCashBoxId() calls security.connectedUser()
        $user = new \App\Dto\Auth\AuthUserDto(userId: 7, username: 'testuser');
        $this->security->expects($this->once())
            ->method('connectedUser')
            ->willReturn($user);

        // entityManager->getRepository() is called by getCashBoxId()
        // willReturn null from the repository means cashShift is null → cajaId = null
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($repository);

        $result = $this->utils->fillSaleValues($sale);

        // cajaId stays null because no open shift was found
        $this->assertNull($result->getCajaId());
    }
}
