<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Sale;

use App\Core\Audit\AuditLogger;
use App\Core\Exception\ServiceException;
use App\EInvoicing\Sale\SaleVoidUseCase;
use App\EInvoicing\Sale\Void\CashRefundRegistrar;
use App\EInvoicing\Sale\Void\SunatVoidResponse;
use App\EInvoicing\Sale\Void\SunatVoidService;
use App\EInvoicing\Sale\Void\TaxDocumentVoidUpdater;
use App\EInvoicing\Sale\Void\VoidStrategyInterface;
use App\EInvoicing\Service\SaleV2Loader;
use App\EInvoicing\Shared\ErrorMessages;
use App\Entity\CashMovementCategory;
use App\Catalog\Entity\PaymentMethod;
use App\Sales\Entity\SaleRecord;
use App\Billing\Entity\TaxDocument;
use App\Enum\CashMovementType;
use App\Billing\Repository\TaxDocumentEventRepository;
use App\Billing\Repository\TaxDocumentRepository;
use App\Sale\Domain\DocType;
use App\Sale\Domain\SaleException;
use App\Sale\Domain\SaleStatus;
use App\State\Sale\CashSessionResolverInterface;
use App\State\Sale\SalesWriteGateway;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SaleVoidUseCaseTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Guard: sale not found
    // ---------------------------------------------------------------------------

    public function testThrowsWhenSaleNotFoundInEitherSource(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn(null);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage(ErrorMessages::SALE_NOT_EXISTS);

        $useCase('sale-404', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // Guard: already voided
    // ---------------------------------------------------------------------------

    public function testThrowsAlreadyVoidedWhenSaleIsVoided(): void
    {
        $sale = (new SaleRecord())->setId('sale-1');
        $sale->setStatus('VOIDED');

        $em = $this->createMock(EntityManagerInterface::class);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('La venta ya ha sido anulada');

        $useCase('sale-1', 'reason', 1);
    }

    public function testThrowsAlreadyVoidedForV2SaleWithVoidedStatus(): void
    {
        // When no movimiento row exists, SaleV2Loader returns a synthetic Sale
        // whose estado must be SaleStatus::VOIDED->value (not 0, which was the old bug).
        $v2Sale = new SaleRecord();
        $v2Sale->setId('v2-uuid');
        $v2Sale->setStatus('VOIDED'); // setEstado(0) or setStatus('VOIDED') — bridge maps estado=0 to VOIDED

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null); // no legacy row

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->with('v2-uuid')->willReturn($v2Sale);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('La venta ya ha sido anulada');

        $useCase('v2-uuid', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // Guard: too old (> 7 days)
    // ---------------------------------------------------------------------------

    public function testThrowsTooOldWhenSaleDateExceedsWindow(): void
    {
        $sale = new SaleRecord();
        $sale->setId('sale-old');
        $sale->setEstado(SaleStatus::ACTIVE->value);
        $sale->setFecha(new \DateTimeImmutable('-10 days'));

        $em = $this->createMock(EntityManagerInterface::class);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('No se puede anular una venta con más de una semana');

        $useCase('sale-old', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // Guard: credit note exists
    // ---------------------------------------------------------------------------

    public function testThrowsWhenSaleHasCreditNote(): void
    {
        $sale = $this->makeActiveSale('sale-cn');

        $em = $this->createMock(EntityManagerInterface::class);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(true);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('No se puede anular una venta que ya tiene nota de crédito');

        $useCase('sale-cn', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // Nota de Venta path
    // ---------------------------------------------------------------------------

    public function testNotaVentaPathVoidsSaleAndReturnsTrueResponse(): void
    {
        $sale = $this->makeActiveSale('sale-nv');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // → NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        $response = $useCase('sale-nv', 'Test reason', 1);

        self::assertTrue($response->success);
        self::assertSame(ErrorMessages::VOID_SUCCESSFUL, $response->message);
    }

    public function testVoidReasonPersistedOnSaleRecord(): void
    {
        $sale = $this->makeActiveSale('sale-reason');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        $useCase('sale-reason', 'Anulación por error de datos', 1);

        self::assertSame('Anulación por error de datos', $sale->getGlosa());
    }

    public function testSaleStatusSetToVoidedOnNotaVentaPath(): void
    {
        $sale = $this->makeActiveSale('sale-status');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        $useCase('sale-status', 'reason', 1);

        self::assertSame('VOIDED', $sale->getStatus());
    }

    public function testEntityManagerFlushIsCalledOnSuccessfulVoid(): void
    {
        $sale = $this->makeActiveSale('sale-flush');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        $useCase('sale-flush', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // Nota de Venta path — salesWriteGateway sync
    // ---------------------------------------------------------------------------

    public function testSyncVoidToSalesV2IsCalledOnNotaVentaPath(): void
    {
        $sale = $this->makeActiveSale('sale-sync');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $salesWriteGateway->expects(self::once())
            ->method('markVoided')
            ->with($sale, 'sync-reason');

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        $useCase('sale-sync', 'sync-reason', 1);
    }

    public function testSyncVoidToSalesV2FailureDoesNotBubbleException(): void
    {
        $sale = $this->makeActiveSale('sale-sync-fail');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $salesWriteGateway->method('markVoided')
            ->willThrowException(new \RuntimeException('V2 sync failed'));

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        // Should NOT throw — sync failure is swallowed and logged
        $response = $useCase('sale-sync-fail', 'reason', 1);
        self::assertTrue($response->success);
    }

    // ---------------------------------------------------------------------------
    // Nota de Venta path — CashRefundRegistrar (via EM persist observation)
    // ---------------------------------------------------------------------------

    public function testCashRefundMovementPersistedWhenSaleHasCashPayment(): void
    {
        $paymentMethod = new PaymentMethod();
        $this->setPrivateProperty($paymentMethod, 'id', PaymentMethod::CASH);

        $sale = $this->makeActiveSale('sale-cash');
        $sale->setTipoPago($paymentMethod);
        $sale->setSerie('B001');
        $sale->setNumero('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        // V2: CashRefundRegistrar uses DBAL Connection + CashSessionResolver
        $dbalConnection = $this->createMock(Connection::class);
        $dbalConnection->method('fetchAssociative')->willReturn(['id' => 7]); // category found
        $dbalConnection->expects(self::once())->method('executeStatement');   // row written

        /** @var MockObject&CashSessionResolverInterface $cashSessionResolver */
        $cashSessionResolver = $this->createMock(CashSessionResolverInterface::class);
        $cashSessionResolver->method('tryResolveForSale')->willReturn(42);   // session found

        $cashRefundRegistrar = new CashRefundRegistrar($dbalConnection, $cashSessionResolver, new NullLogger());

        $emConnection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($emConnection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            salesWriteGateway: $salesWriteGateway,
            cashRefundRegistrar: $cashRefundRegistrar,
        );

        $response = $useCase('sale-cash', 'Cash void', 1);
        self::assertTrue($response->success);
    }

    public function testCashRefundMovementNotPersistedWhenSaleHasNoCashPayment(): void
    {
        // No tipo_pago set → CashRefundRegistrar exits immediately, no DB access
        $sale = $this->makeActiveSale('sale-nocash');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->method('flush');

        $emConnection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($emConnection);

        // V2: Connection and resolver must NOT be invoked (early return on non-cash payment)
        $dbalConnection = $this->createMock(Connection::class);
        $dbalConnection->expects(self::never())->method('fetchAssociative');
        $dbalConnection->expects(self::never())->method('executeStatement');

        /** @var MockObject&CashSessionResolverInterface $cashSessionResolver */
        $cashSessionResolver = $this->createMock(CashSessionResolverInterface::class);
        $cashSessionResolver->expects(self::never())->method('tryResolveForSale');

        $cashRefundRegistrar = new CashRefundRegistrar($dbalConnection, $cashSessionResolver, new NullLogger());

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            salesWriteGateway: $salesWriteGateway,
            cashRefundRegistrar: $cashRefundRegistrar,
        );

        $useCase('sale-nocash', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // SUNAT invoice (Factura / Boleta) path
    // ---------------------------------------------------------------------------

    public function testFacturaPathCallsSunatVoidServiceAndReturnsSuccess(): void
    {
        $sale = $this->makeActiveSale('sale-factura');

        // Build a real SunatVoidService injected with a mock strategy via reflection
        $sunatVoidResponse = $this->makeSunatVoidResponse(success: true);
        $strategy = $this->createMock(VoidStrategyInterface::class);
        $strategy->method('supports')->willReturn(true);
        $strategy->method('void')->willReturn($sunatVoidResponse);

        $sunatVoidService = $this->buildSunatVoidService($strategy);

        // TaxDocument stub returned by the repository
        $taxDocument = $this->createMock(TaxDocument::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');
        $em->method('persist');

        $connection = $this->createMock(Connection::class);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(DocType::FACTURA->value);
        $taxDocumentRepository->method('findOneBy')->willReturn($taxDocument);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $taxDocumentVoidUpdater = $this->buildTaxDocumentVoidUpdater($em);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            sunatVoidService: $sunatVoidService,
            salesWriteGateway: $salesWriteGateway,
            taxDocumentVoidUpdater: $taxDocumentVoidUpdater,
        );

        $response = $useCase('sale-factura', 'Factura void', 1);

        self::assertTrue($response->success);
        self::assertSame(ErrorMessages::VOID_SUCCESSFUL, $response->message);
    }

    public function testBoletaPathCallsSunatVoidServiceAndReturnsSuccess(): void
    {
        $sale = $this->makeActiveSale('sale-boleta');

        $sunatVoidResponse = $this->makeSunatVoidResponse(success: true);
        $strategy = $this->createMock(VoidStrategyInterface::class);
        $strategy->method('supports')->willReturn(true);
        $strategy->method('void')->willReturn($sunatVoidResponse);

        $sunatVoidService = $this->buildSunatVoidService($strategy);

        $taxDocument = $this->createMock(TaxDocument::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');
        $em->method('persist');

        $connection = $this->createMock(Connection::class);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(DocType::BOLETA->value);
        $taxDocumentRepository->method('findOneBy')->willReturn($taxDocument);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $taxDocumentVoidUpdater = $this->buildTaxDocumentVoidUpdater($em);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            sunatVoidService: $sunatVoidService,
            salesWriteGateway: $salesWriteGateway,
            taxDocumentVoidUpdater: $taxDocumentVoidUpdater,
        );

        $response = $useCase('sale-boleta', 'Boleta void', 1);

        self::assertTrue($response->success);
    }

    public function testSunatPathReturnsFailureMessageWhenSunatResponseIsFalse(): void
    {
        $sale = $this->makeActiveSale('sale-fail');

        $sunatVoidResponse = $this->makeSunatVoidResponse(success: false, message: 'SUNAT rechazó la anulación');
        $strategy = $this->createMock(VoidStrategyInterface::class);
        $strategy->method('supports')->willReturn(true);
        $strategy->method('void')->willReturn($sunatVoidResponse);

        $sunatVoidService = $this->buildSunatVoidService($strategy);

        $taxDocument = $this->createMock(TaxDocument::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');
        $em->method('persist');

        $connection = $this->createMock(Connection::class);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(DocType::FACTURA->value);
        $taxDocumentRepository->method('findOneBy')->willReturn($taxDocument);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);
        $taxDocumentVoidUpdater = $this->buildTaxDocumentVoidUpdater($em);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            sunatVoidService: $sunatVoidService,
            salesWriteGateway: $salesWriteGateway,
            taxDocumentVoidUpdater: $taxDocumentVoidUpdater,
        );

        $response = $useCase('sale-fail', 'reason', 1);

        self::assertFalse($response->success);
        self::assertSame('SUNAT rechazó la anulación', $response->message);
    }

    public function testSunatPathRollsBackOnExceptionAndRethrows(): void
    {
        $sale = $this->makeActiveSale('sale-rollback');

        $strategy = $this->createMock(VoidStrategyInterface::class);
        $strategy->method('supports')->willReturn(true);
        $strategy->method('void')->willThrowException(new \RuntimeException('SUNAT network error'));

        $sunatVoidService = $this->buildSunatVoidService($strategy);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($sale);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(DocType::FACTURA->value);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            sunatVoidService: $sunatVoidService,
            salesWriteGateway: $salesWriteGateway,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SUNAT network error');

        $useCase('sale-rollback', 'reason', 1);
    }

    public function testThrowsForUnsupportedDocumentType(): void
    {
        $sale = $this->makeActiveSale('sale-unsupported');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($sale);

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        // Document type '09' is not NOTA_VENTA, FACTURA, or BOLETA → ServiceException
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn('09');

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage(ErrorMessages::UNSUPPORTED_VOUCHER_TYPE_FOR_VOID);

        $useCase('sale-unsupported', 'reason', 1);
    }

    public function testSunatPathThrowsWhenTaxDocumentNotFound(): void
    {
        $sale = $this->makeActiveSale('sale-notaxdoc');

        $sunatVoidResponse = $this->makeSunatVoidResponse(success: true);
        $strategy = $this->createMock(VoidStrategyInterface::class);
        $strategy->method('supports')->willReturn(true);
        $strategy->method('void')->willReturn($sunatVoidResponse);

        $sunatVoidService = $this->buildSunatVoidService($strategy);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($sale);

        $connection = $this->createMock(Connection::class);
        $connection->method('beginTransaction');
        $connection->method('rollBack');
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(DocType::FACTURA->value);
        $taxDocumentRepository->method('findOneBy')->willReturn(null); // not found

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($sale);
        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase(
            $em,
            $taxDocumentRepository,
            $saleV2Loader,
            sunatVoidService: $sunatVoidService,
            salesWriteGateway: $salesWriteGateway,
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage(ErrorMessages::TAX_DOCUMENT_NOT_FOUND);

        $useCase('sale-notaxdoc', 'reason', 1);
    }

    // ---------------------------------------------------------------------------
    // V2 loader fallback
    // ---------------------------------------------------------------------------

    public function testUsesV2LoaderFallbackWhenLegacySaleNotFound(): void
    {
        // Sale with no payment method → CashRefundRegistrar.register() exits early
        // (no EntityManager access needed inside CashRefundRegistrar).
        $v2Sale = new SaleRecord();
        $v2Sale->setId('v2-uuid');
        $v2Sale->setEstado(SaleStatus::ACTIVE->value);
        $v2Sale->setFecha(new \DateTimeImmutable('today'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null); // → NOTA_VENTA

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->expects(self::once())
            ->method('loadAsSale')
            ->with('v2-uuid')
            ->willReturn($v2Sale);

        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        // NOTA_VENTA path with no legacy persist — should succeed.
        $response = $useCase('v2-uuid', 'Test void', 1);

        self::assertTrue($response->success);
    }

    public function testDoesNotPersistSaleWhenLoadedFromV2(): void
    {
        // Sale with no payment method → CashRefundRegistrar exits early.
        $v2Sale = new SaleRecord();
        $v2Sale->setId('v2-only');
        $v2Sale->setEstado(SaleStatus::ACTIVE->value);
        $v2Sale->setFecha(new \DateTimeImmutable('today'));

        $em = $this->createMock(EntityManagerInterface::class);
        // persist must NOT be called for a synthetic V2 Sale
        $em->expects(self::never())->method('persist');
        $em->method('flush');

        $connection = $this->createMock(Connection::class);
        $em->method('getConnection')->willReturn($connection);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $taxDocumentRepository->method('hasCreditNoteForSale')->willReturn(false);
        $taxDocumentRepository->method('findDocumentTypeBySaleId')->willReturn(null);

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn($v2Sale);

        $salesWriteGateway = $this->createMock(SalesWriteGateway::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader, salesWriteGateway: $salesWriteGateway);

        $useCase('v2-only', 'Void reason', 1);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeActiveSale(string $id): SaleRecord
    {
        $sale = new SaleRecord();
        $sale->setId($id);
        $sale->setEstado(SaleStatus::ACTIVE->value);
        $sale->setFecha(new \DateTimeImmutable('today'));

        return $sale;
    }

    /**
     * Build a SunatVoidService (final readonly) with a mock strategy injected via reflection.
     */
    private function buildSunatVoidService(VoidStrategyInterface $strategy): SunatVoidService
    {
        $sunatVoidService = (new \ReflectionClass(SunatVoidService::class))->newInstanceWithoutConstructor();
        $prop = (new \ReflectionClass(SunatVoidService::class))->getProperty('strategies');
        $prop->setValue($sunatVoidService, [$strategy]);

        return $sunatVoidService;
    }

    private function makeSunatVoidResponse(bool $success, ?string $message = null): SunatVoidResponse
    {
        /** @var \App\EInvoicing\Billing\Model\Response\SummaryResponse $rawResponse */
        $rawResponse = (new \ReflectionClass(\App\EInvoicing\Billing\Model\Response\SummaryResponse::class))
            ->newInstanceWithoutConstructor();

        return new SunatVoidResponse(
            rawResponse: $rawResponse,
            name: 'RA-20240101-00001',
            ticket: 'T001',
            success: $success,
            message: $message,
        );
    }

    /**
     * Set a private/protected property value on an object via reflection.
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed  $value
     */
    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $prop = (new \ReflectionClass($object))->getProperty($propertyName);
        $prop->setValue($object, $value);
    }

    /**
     * Build a TaxDocumentVoidUpdater with mocked dependencies (for SUNAT path tests).
     * TaxDocumentVoidUpdater is final readonly, so we construct it normally with mocks.
     * TaxDocumentEventRepository is also final, so we bypass its constructor via reflection
     * and inject a mock ManagerRegistry so that recordEvent() can call getManager()->persist().
     */
    private function buildTaxDocumentVoidUpdater(
        ?EntityManagerInterface $em = null,
    ): TaxDocumentVoidUpdater {
        /** @var TaxDocumentEventRepository $taxDocEventRepo */
        $taxDocEventRepo = (new \ReflectionClass(TaxDocumentEventRepository::class))->newInstanceWithoutConstructor();

        // Wire a mock ManagerRegistry so that recordEvent() → registry->getManager()->persist() works
        $mockObjectManager = $this->createMock(ObjectManager::class);
        $mockObjectManager->method('persist'); // no-op

        $mockRegistry = $this->createMock(ManagerRegistry::class);
        $mockRegistry->method('getManager')->willReturn($mockObjectManager);

        $registryProp = (new \ReflectionClass(TaxDocumentEventRepository::class))->getProperty('registry');
        $registryProp->setValue($taxDocEventRepo, $mockRegistry);

        return new TaxDocumentVoidUpdater(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $taxDocEventRepo,
        );
    }

    private function buildUseCase(
        EntityManagerInterface $em,
        TaxDocumentRepository $taxDocumentRepository,
        SaleV2Loader $saleV2Loader,
        ?SalesWriteGateway $salesWriteGateway = null,
        ?SunatVoidService $sunatVoidService = null,
        ?CashRefundRegistrar $cashRefundRegistrar = null,
        ?TaxDocumentVoidUpdater $taxDocumentVoidUpdater = null,
    ): SaleVoidUseCase {
        if ($sunatVoidService === null) {
            /** @var SunatVoidService $sunatVoidService */
            $sunatVoidService = (new \ReflectionClass(SunatVoidService::class))->newInstanceWithoutConstructor();
        }

        if ($cashRefundRegistrar === null) {
            /** @var CashRefundRegistrar $cashRefundRegistrar */
            $cashRefundRegistrar = (new \ReflectionClass(CashRefundRegistrar::class))->newInstanceWithoutConstructor();
        }

        if ($taxDocumentVoidUpdater === null) {
            /** @var TaxDocumentVoidUpdater $taxDocumentVoidUpdater */
            $taxDocumentVoidUpdater = (new \ReflectionClass(TaxDocumentVoidUpdater::class))->newInstanceWithoutConstructor();
        }

        return new SaleVoidUseCase(
            entityManager: $em,
            taxDocumentRepository: $taxDocumentRepository,
            sunatVoidService: $sunatVoidService,
            taxDocumentVoidUpdater: $taxDocumentVoidUpdater,
            cashRefundRegistrar: $cashRefundRegistrar,
            auditLogger: $this->createMock(AuditLogger::class),
            salesWriteGateway: $salesWriteGateway ?? $this->createMock(SalesWriteGateway::class),
            saleV2Loader: $saleV2Loader,
        );
    }
}
