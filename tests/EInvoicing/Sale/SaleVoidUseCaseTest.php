<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Sale;

use App\Core\Audit\AuditLogger;
use App\Core\Exception\ServiceException;
use App\EInvoicing\Sale\SaleVoidUseCase;
use App\EInvoicing\Sale\Void\CashRefundRegistrar;
use App\EInvoicing\Sale\Void\SunatVoidService;
use App\EInvoicing\Sale\Void\TaxDocumentVoidUpdater;
use App\EInvoicing\Service\SaleV2Loader;
use App\EInvoicing\Shared\ErrorMessages;
use App\Entity\Sale;
use App\Repository\TaxDocumentRepository;
use App\Sale\Domain\SaleException;
use App\Sale\Domain\SaleStatus;
use App\State\Sale\SalesWriteGateway;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SaleVoidUseCaseTest extends TestCase
{
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

    public function testThrowsAlreadyVoidedWhenSaleIsVoided(): void
    {
        $sale = (new Sale())->setId('sale-1');
        $sale->setEstado(SaleStatus::VOIDED->value);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($sale);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->expects(self::never())->method('loadAsSale');

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('La venta ya ha sido anulada');

        $useCase('sale-1', 'reason', 1);
    }

    public function testThrowsAlreadyVoidedForV2SaleWithVoidedStatus(): void
    {
        // When no movimiento row exists, SaleV2Loader returns a synthetic Sale
        // whose estado must be SaleStatus::VOIDED->value (not 0, which was the old bug).
        $v2Sale = new Sale();
        $v2Sale->setId('v2-uuid');
        $v2Sale->setEstado(SaleStatus::VOIDED->value); // must be 2, not 0

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

    public function testThrowsTooOldWhenSaleDateExceedsWindow(): void
    {
        $sale = new Sale();
        $sale->setId('sale-old');
        $sale->setEstado(SaleStatus::ACTIVE->value);
        $sale->setFecha(new \DateTimeImmutable('-10 days'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($sale);

        $taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $saleV2Loader = $this->createMock(SaleV2Loader::class);

        $useCase = $this->buildUseCase($em, $taxDocumentRepository, $saleV2Loader);

        $this->expectException(SaleException::class);
        $this->expectExceptionMessage('No se puede anular una venta con más de una semana');

        $useCase('sale-old', 'reason', 1);
    }

    public function testUsesV2LoaderFallbackWhenLegacySaleNotFound(): void
    {
        // Sale with no payment method → CashRefundRegistrar.register() exits early
        // (no EntityManager access needed inside CashRefundRegistrar).
        $v2Sale = new Sale();
        $v2Sale->setId('v2-uuid');
        $v2Sale->setEstado(SaleStatus::ACTIVE->value);
        $v2Sale->setFecha(new \DateTimeImmutable('today'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
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
        $v2Sale = new Sale();
        $v2Sale->setId('v2-only');
        $v2Sale->setEstado(SaleStatus::ACTIVE->value);
        $v2Sale->setFecha(new \DateTimeImmutable('today'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
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

    private function buildUseCase(
        EntityManagerInterface $em,
        TaxDocumentRepository $taxDocumentRepository,
        SaleV2Loader $saleV2Loader,
        ?SalesWriteGateway $salesWriteGateway = null,
    ): SaleVoidUseCase {
        // SunatVoidService, TaxDocumentVoidUpdater and CashRefundRegistrar are final,
        // so we create them without calling their constructors.
        // Tests that throw early never invoke these services.
        // Tests reaching NOTA_VENTA path rely on Sale having no payment method,
        // which makes CashRefundRegistrar.register() exit immediately.
        /** @var SunatVoidService $sunatVoidService */
        $sunatVoidService = (new \ReflectionClass(SunatVoidService::class))->newInstanceWithoutConstructor();

        /** @var TaxDocumentVoidUpdater $taxDocumentVoidUpdater */
        $taxDocumentVoidUpdater = (new \ReflectionClass(TaxDocumentVoidUpdater::class))->newInstanceWithoutConstructor();

        /** @var CashRefundRegistrar $cashRefundRegistrar */
        $cashRefundRegistrar = (new \ReflectionClass(CashRefundRegistrar::class))->newInstanceWithoutConstructor();

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
