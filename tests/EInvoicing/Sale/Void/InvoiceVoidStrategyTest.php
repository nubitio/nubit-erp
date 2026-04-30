<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Sale\Void;

use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;
use App\EInvoicing\Billing\Domain\VoidedCreateUseCase;
use App\EInvoicing\Billing\Model\Response\SummaryResponse;
use App\EInvoicing\Sale\Void\InvoiceVoidStrategy;
use App\EInvoicing\Sale\Void\SunatVoidResponse;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use App\Sales\Domain\DocType;
use App\Sales\Domain\SaleException;
use Greenter\Model\Response\SummaryResult;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class InvoiceVoidStrategyTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeFileManager(): FileManager
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $tenantContext = new TenantContext();
        $slugger = new AsciiSlugger();

        return new FileManager($filesystem, $tenantContext, $slugger);
    }

    private function makeSummaryResponse(
        string $name = 'RA-20250101-00001',
        string $xml = '<xml/>',
        string $hash = 'hash-abc',
        bool $success = true,
        string $ticket = 'TICKET-001',
    ): SummaryResponse {
        $result = new SummaryResult();
        $result->setSuccess($success)->setTicket($ticket);

        $response = new SummaryResponse();
        $response->name = $name;
        $response->xml = $xml;
        $response->hash = $hash;
        $response->sunatResponse = $result;

        return $response;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // supports()
    // ──────────────────────────────────────────────────────────────────────────

    public function testSupportsFactura(): void
    {
        $sut = new InvoiceVoidStrategy(
            $this->createMock(TaxDocumentRepository::class),
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        self::assertTrue($sut->supports(DocType::FACTURA->value));
    }

    public function testDoesNotSupportBoleta(): void
    {
        $sut = new InvoiceVoidStrategy(
            $this->createMock(TaxDocumentRepository::class),
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        self::assertFalse($sut->supports(DocType::BOLETA->value));
    }

    public function testDoesNotSupportArbitraryType(): void
    {
        $sut = new InvoiceVoidStrategy(
            $this->createMock(TaxDocumentRepository::class),
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        self::assertFalse($sut->supports('99'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // void() — document not found
    // ──────────────────────────────────────────────────────────────────────────

    public function testVoidThrowsServiceExceptionWhenTaxDocumentNotFound(): void
    {
        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $sut = new InvoiceVoidStrategy(
            $repo,
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        $this->expectException(ServiceException::class);

        $sut->void('sale-missing', 'Error de prueba');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // void() — document not issued (sunat_status !== '0')
    // ──────────────────────────────────────────────────────────────────────────

    public function testVoidThrowsSaleExceptionWhenDocumentNotIssued(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getSunatStatus')->willReturn('1'); // not '0'

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')->willReturn($taxDocument);

        $sut = new InvoiceVoidStrategy(
            $repo,
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        $this->expectException(SaleException::class);

        $sut->void('sale-001', 'Anular');
    }

    public function testVoidThrowsSaleExceptionWhenSunatStatusIsNull(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getSunatStatus')->willReturn(null);

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')->willReturn($taxDocument);

        $sut = new InvoiceVoidStrategy(
            $repo,
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        $this->expectException(SaleException::class);

        $sut->void('sale-001', 'Anular');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // void() — success path
    // ──────────────────────────────────────────────────────────────────────────

    public function testVoidReturnsSunatVoidResponseOnSuccess(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getSunatStatus')->willReturn('0');
        $taxDocument->method('getId')->willReturn('42');

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')->willReturn($taxDocument);

        $summaryResponse = $this->makeSummaryResponse(
            name: 'RA-20250101-00001',
            success: true,
            ticket: 'TICKET-001',
        );

        $useCase = $this->createStub(VoidedCreateUseCase::class);
        $useCase->method('__invoke')->willReturn($summaryResponse);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())->method('write'); // file must be written on success

        $fileManager = new FileManager($filesystem, new TenantContext(), new AsciiSlugger());

        $sut = new InvoiceVoidStrategy($repo, $useCase, $fileManager);

        $result = $sut->void('sale-001', 'Anular por error');

        self::assertInstanceOf(SunatVoidResponse::class, $result);
        self::assertTrue($result->success);
        self::assertSame('RA-20250101-00001', $result->name);
        self::assertSame('TICKET-001', $result->ticket);
        self::assertSame('Solicitud de anulación aceptada', $result->message);
    }

    public function testVoidDoesNotWriteFileOnFailure(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getSunatStatus')->willReturn('0');
        $taxDocument->method('getId')->willReturn('99');

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')->willReturn($taxDocument);

        $summaryResponse = $this->makeSummaryResponse(success: false, ticket: '');

        $useCase = $this->createStub(VoidedCreateUseCase::class);
        $useCase->method('__invoke')->willReturn($summaryResponse);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::never())->method('write'); // no file write on failure

        $fileManager = new FileManager($filesystem, new TenantContext(), new AsciiSlugger());

        $sut = new InvoiceVoidStrategy($repo, $useCase, $fileManager);

        $result = $sut->void('sale-002', 'Anular');

        self::assertFalse($result->success);
    }

    public function testVoidCallsUseCaseWithTaxDocumentIdAndReason(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getSunatStatus')->willReturn('0');
        $taxDocument->method('getId')->willReturn('77');

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')->willReturn($taxDocument);

        $summaryResponse = $this->makeSummaryResponse();

        $useCase = $this->createMock(VoidedCreateUseCase::class);
        $useCase->expects(self::once())
            ->method('__invoke')
            ->with('77', 'razón de anulación')
            ->willReturn($summaryResponse);

        $filesystem = $this->createStub(FilesystemOperator::class);
        $fileManager = new FileManager($filesystem, new TenantContext(), new AsciiSlugger());

        $sut = new InvoiceVoidStrategy($repo, $useCase, $fileManager);
        $sut->void('sale-003', 'razón de anulación');
    }

    public function testVoidQueriesRepoWithFacturaDocType(): void
    {
        $capturedCriteria = null;

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use (&$capturedCriteria): null {
                $capturedCriteria = $criteria;
                return null;
            });

        $sut = new InvoiceVoidStrategy(
            $repo,
            $this->createMock(VoidedCreateUseCase::class),
            $this->makeFileManager(),
        );

        try {
            $sut->void('sale-004', 'Anular');
        } catch (ServiceException) {
            // expected
        }

        self::assertIsArray($capturedCriteria);
        self::assertSame(DocType::FACTURA->value, $capturedCriteria['documentType'] ?? null);
        self::assertSame('sale-004', $capturedCriteria['saleRecord'] ?? null);
    }
}
