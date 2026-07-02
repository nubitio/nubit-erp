<?php

declare(strict_types=1);

namespace App\Tests\Billing\Sale\Void;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sale\Void\ReceiptVoidStrategy;
use App\Billing\Sale\Void\SunatVoidResponse;
use App\Billing\Sunat\Domain\SummaryCreateUseCase;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use App\Sales\Domain\DocType;
use App\Sales\Domain\SaleException;
use Greenter\Model\Response\SummaryResult;
use League\Flysystem\FilesystemOperator;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ReceiptVoidStrategyTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeFileManager(?FilesystemOperator $filesystem = null): FileManager
    {
        $fs = $filesystem ?? $this->createMock(FilesystemOperator::class);

        return new FileManager($fs, new TenantContext(), new AsciiSlugger());
    }

    private function makeSummaryResponse(
        string $name = 'RC-20250101-00001',
        string $xml = '<xml/>',
        string $hash = 'hash-xyz',
        bool $success = true,
        string $ticket = 'TICKET-002',
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

    public function testSupportsBoleta(): void
    {
        $sut = new ReceiptVoidStrategy(
            $this->createMock(TaxDocumentRepository::class),
            $this->createMock(SummaryCreateUseCase::class),
            $this->makeFileManager(),
            new NullLogger(),
        );

        self::assertTrue($sut->supports(DocType::BOLETA->value));
    }

    public function testDoesNotSupportFactura(): void
    {
        $sut = new ReceiptVoidStrategy(
            $this->createMock(TaxDocumentRepository::class),
            $this->createMock(SummaryCreateUseCase::class),
            $this->makeFileManager(),
            new NullLogger(),
        );

        self::assertFalse($sut->supports(DocType::FACTURA->value));
    }

    public function testDoesNotSupportArbitraryType(): void
    {
        $sut = new ReceiptVoidStrategy(
            $this->createMock(TaxDocumentRepository::class),
            $this->createMock(SummaryCreateUseCase::class),
            $this->makeFileManager(),
            new NullLogger(),
        );

        self::assertFalse($sut->supports('99'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // void() — document not found
    // ──────────────────────────────────────────────────────────────────────────

    public function testVoidThrowsSaleExceptionWhenTaxDocumentNotFound(): void
    {
        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findLatestAcceptedBySaleAndType')->willReturn(null);

        $sut = new ReceiptVoidStrategy(
            $repo,
            $this->createMock(SummaryCreateUseCase::class),
            $this->makeFileManager(),
            new NullLogger(),
        );

        $this->expectException(SaleException::class);

        $sut->void('sale-missing', 'Error de prueba');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // void() — success path
    // ──────────────────────────────────────────────────────────────────────────

    public function testVoidReturnsSunatVoidResponseOnSuccess(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getId')->willReturn('55');

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findLatestAcceptedBySaleAndType')->willReturn($taxDocument);

        $summaryResponse = $this->makeSummaryResponse(name: 'RC-20250101-00001', success: true, ticket: 'TICKET-002');

        $useCase = $this->createStub(SummaryCreateUseCase::class);
        $useCase->method('__invoke')->willReturn($summaryResponse);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())->method('write'); // file written on success

        $sut = new ReceiptVoidStrategy($repo, $useCase, $this->makeFileManager($filesystem), new NullLogger());

        $result = $sut->void('sale-001', 'Anular boleta');

        self::assertInstanceOf(SunatVoidResponse::class, $result);
        self::assertTrue($result->success);
        self::assertSame('RC-20250101-00001', $result->name);
        self::assertSame('TICKET-002', $result->ticket);
        self::assertSame('Solicitud de anulación aceptada', $result->message);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // void() — failure path (sunat returns error)
    // ──────────────────────────────────────────────────────────────────────────

    public function testVoidThrowsServiceExceptionWhenSunatResponseFails(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getId')->willReturn('66');

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findLatestAcceptedBySaleAndType')->willReturn($taxDocument);

        $summaryResponse = $this->makeSummaryResponse(success: false, ticket: '');

        $useCase = $this->createStub(SummaryCreateUseCase::class);
        $useCase->method('__invoke')->willReturn($summaryResponse);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::never())->method('write'); // no file on failure

        $sut = new ReceiptVoidStrategy($repo, $useCase, $this->makeFileManager($filesystem), new NullLogger());

        $this->expectException(ServiceException::class);

        $sut->void('sale-002', 'Anular');
    }

    public function testVoidCallsUseCaseWithTaxDocumentId(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $taxDocument->method('getId')->willReturn('88');

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findLatestAcceptedBySaleAndType')->willReturn($taxDocument);

        $summaryResponse = $this->makeSummaryResponse();

        $useCase = $this->createMock(SummaryCreateUseCase::class);
        $useCase->expects(self::once())->method('__invoke')->with('88')->willReturn($summaryResponse);

        $filesystem = $this->createStub(FilesystemOperator::class);

        $sut = new ReceiptVoidStrategy($repo, $useCase, $this->makeFileManager($filesystem), new NullLogger());

        $sut->void('sale-003', 'Anular boleta');
    }

    public function testVoidQueriesRepoWithBoletaDocType(): void
    {
        $capturedSaleId = null;
        $capturedDocType = null;

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('findLatestAcceptedBySaleAndType')->willReturnCallback(static function (
            string $saleId,
            string $docType,
        ) use (&$capturedSaleId, &$capturedDocType): null {
            $capturedSaleId = $saleId;
            $capturedDocType = $docType;
            return null;
        });

        $sut = new ReceiptVoidStrategy(
            $repo,
            $this->createMock(SummaryCreateUseCase::class),
            $this->makeFileManager(),
            new NullLogger(),
        );

        try {
            $sut->void('sale-004', 'Anular');
        } catch (SaleException) {
            // expected
        }

        self::assertSame('sale-004', $capturedSaleId);
        self::assertSame(DocType::BOLETA->value, $capturedDocType);
    }
}
