<?php

declare(strict_types=1);

namespace App\Tests\Billing\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Billing\Dto\SeriesResultDto;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Service\CreditNoteLinePreparer;
use App\Billing\Service\CreditNoteReturnSelector;
use App\Billing\Service\CreditNoteStockReverserInterface;
use App\Billing\State\TaxDocumentProcessor;
use App\Identity\Activity\SalesActivityInterface;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Auth\OperationalBranchGuard;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\CreditNoteReceivableAdjuster;
use App\Settings\Service\TenantSettingsService;
use Nubit\Platform\Exception\ServiceException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;

/**
 * Unit tests for TaxDocumentProcessor::process().
 *
 * TaxDocumentProcessor is a readonly class; constructor dependencies are final or
 * heavyweight (Doctrine repositories). We bypass the constructor via
 * ReflectionClass::newInstanceWithoutConstructor() and inject only the mocks
 * exercised by each scenario.
 *
 * Covers:
 * - TC-1: Happy path — new credit note (type 07) referencing an existing invoice is processed successfully.
 * - TC-2: Series mismatch — associated series doesn't match affected document prefix → ServiceException.
 * - TC-3: Voided document guard — credit note for a voided related document → ServiceException.
 * - TC-4: Delete guard — deleting a processed (sent to SUNAT) document → ServiceException.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TaxDocumentProcessorTest extends TestCase
{
    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $persistProcessor;

    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $removeProcessor;

    /** @var BranchContextServiceInterface */
    private BranchContextServiceInterface $branchContextService;

    /** @var BranchRepository&Stub */
    private BranchRepository $branchRepository;

    /** @var TaxDocumentRepository&MockObject */
    private TaxDocumentRepository $taxDocumentRepository;

    protected function setUp(): void
    {
        // Use createMock() only for objects on which test methods place explicit
        // expectations (expects()->method()). Use createStub() for all others.
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $this->removeProcessor = $this->createMock(ProcessorInterface::class);
        $this->branchRepository = $this->createStub(BranchRepository::class);
        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, 'branch-001');
        $this->branchRepository->method('find')->willReturn($branch);
        $this->taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $this->branchContextService = $this->buildBranchContextStub(branchId: 'branch-001');
    }

    // -------------------------------------------------------------------------
    // TC-1: Happy path — type 07 credit note processed successfully
    // -------------------------------------------------------------------------

    /**
     * A new credit note (reasonCode set, no id yet) that references a live (non-voided)
     * invoice whose series matches the credit-note series association.
     * Expect: persistProcessor->process() is called once and the document type is '07'.
     */
    public function testNewCreditNoteIsProcessedSuccessfully(): void
    {
        $relatedDoc = $this->buildRelatedTaxDocument(isVoided: false, series: 'F001');

        $creditNote = $this->buildNewCreditNote(relatedTaxDocument: $relatedDoc, reasonCode: '01');

        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));

        $this->taxDocumentRepository->method('getNextSequenceNumber')->willReturn(1);

        $this->taxDocumentRepository
            ->method('getSalesCurrencyForSaleId')
            ->willReturn(['currency' => 'PEN', 'exchange_rate' => null]);

        $this->persistProcessor->expects(self::once())->method('process')->willReturnArgument(0);

        $processor = $this->buildProcessor();
        $result = $processor->process($creditNote, new Post(), [], []);

        self::assertInstanceOf(TaxDocument::class, $result);
        self::assertSame('07', $result->getDocumentType());
        self::assertSame('BC01', $result->getSeries());
        self::assertSame('1', $result->getNumber());
    }

    /**
     * G-04: a new credit note on a credit sale hands the sale and the note's total to the receivable adjuster,
     * so the open balance goes down in the same transaction.
     */
    public function testNewCreditNoteAdjustsTheReceivableOfItsSale(): void
    {
        $sale = new SaleRecord();
        $relatedDoc = $this->buildRelatedTaxDocument(isVoided: false, series: 'F001');
        $relatedDoc->setSaleRecord($sale);
        $creditNote = $this->buildNewCreditNote(relatedTaxDocument: $relatedDoc, reasonCode: '04');

        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));
        $this->taxDocumentRepository->method('getNextSequenceNumber')->willReturn(1);
        $this->taxDocumentRepository
            ->method('getSalesCurrencyForSaleId')
            ->willReturn(['currency' => 'PEN', 'exchange_rate' => null]);
        // The database assigns the id when the note is persisted.
        $this->persistProcessor
            ->method('process')
            ->willReturnCallback(static function (TaxDocument $note): TaxDocument {
                new \ReflectionProperty(TaxDocument::class, 'id')->setValue($note, 'cn-1');

                return $note;
            });

        $adjuster = $this->createMock(CreditNoteReceivableAdjuster::class);
        $adjuster
            ->expects(self::once())
            ->method('apply')
            ->with($sale, self::anything(), 'cn-1', self::anything(), self::anything())
            ->willReturn(['reduced' => '0.00', 'creditedToCustomer' => '0.00']);

        $this->buildProcessor($adjuster)->process($creditNote, new Post(), [], []);
    }

    // -------------------------------------------------------------------------
    // TC-2: Series mismatch — series of credit note doesn't match invoice prefix
    // -------------------------------------------------------------------------

    /**
     * The SeriesResultDto's associatedSeries ('F001') does not match the first 4 characters
     * of the affected document stored on the sale ('B001-00123').
     * Expect: ServiceException with the series mismatch message.
     */
    public function testSeriesMismatchThrowsServiceException(): void
    {
        // The sale's invoice field starts with 'B001' — different from associatedSeries 'F001'.
        $relatedDoc = $this->buildRelatedTaxDocument(isVoided: false, series: 'B001');

        $creditNote = $this->buildNewCreditNote(relatedTaxDocument: $relatedDoc, reasonCode: '01');

        // Branch's configured series expects F001 invoices but the sale has a B001 invoice.
        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));

        $this->taxDocumentRepository->method('getNextSequenceNumber')->willReturn(1);

        $this->persistProcessor->expects(self::never())->method('process');

        $processor = $this->buildProcessor();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage(
            'La serie de la nota de crédito no coincide con la serie del documento afectado.',
        );

        $processor->process($creditNote, new Post(), [], []);
    }

    // -------------------------------------------------------------------------
    // TC-3: Voided document guard — cannot create credit note for voided document
    // -------------------------------------------------------------------------

    /**
     * The related TaxDocument is voided (voidTicket is set).
     * Expect: ServiceException reporting the related document is voided.
     */
    public function testVoidedRelatedDocumentThrowsServiceException(): void
    {
        $relatedDoc = $this->buildRelatedTaxDocument(isVoided: true, series: 'F001');

        $creditNote = $this->buildNewCreditNote(relatedTaxDocument: $relatedDoc, reasonCode: '01');

        $this->persistProcessor->expects(self::never())->method('process');

        $processor = $this->buildProcessor();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No se puede generar nota de crédito para un comprobante anulado.');

        $processor->process($creditNote, new Post(), [], []);
    }

    public function testPartialReturnsCannotExceedOriginalQuantityCumulatively(): void
    {
        $relatedDoc = $this->buildRelatedTaxDocument(isVoided: false, series: 'F001');
        $creditNote = $this->buildNewCreditNote(relatedTaxDocument: $relatedDoc, reasonCode: '07');
        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));
        $this->taxDocumentRepository->method('getNextSequenceNumber')->willReturn(1);
        $this->taxDocumentRepository->method('getReturnedQuantity')->with($relatedDoc, 1)->willReturn(1.0);

        $this->persistProcessor->expects(self::never())->method('process');
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no puede exceder');

        $this->buildProcessor()->process($creditNote, new Post(), [], []);
    }

    public function testDuplicateRequestedLinesAreAggregatedBeforeValidatingQuantity(): void
    {
        $relatedDoc = $this->buildRelatedTaxDocument(isVoided: false, series: 'F001');
        $creditNote = $this->buildNewCreditNote(relatedTaxDocument: $relatedDoc, reasonCode: '07');
        $firstLine = $creditNote->getLines()->first();
        self::assertInstanceOf(TaxDocumentLine::class, $firstLine);
        $firstLine->setQuantity('0.600000');
        $duplicate = new TaxDocumentLine();
        $duplicate->setLineNumber(1);
        $duplicate->setProductName('Test product');
        $duplicate->setQuantity('0.600000');
        $duplicate->setUnitPrice('100.000000');
        $creditNote->addLine($duplicate);
        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));
        $this->taxDocumentRepository->method('getNextSequenceNumber')->willReturn(1);

        $this->persistProcessor->expects(self::never())->method('process');
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no puede exceder');

        $this->buildProcessor()->process($creditNote, new Post(), [], []);
    }

    // -------------------------------------------------------------------------
    // TC-4: Delete guard — cannot delete a processed (SUNAT-acknowledged) document
    // -------------------------------------------------------------------------

    /**
     * A DELETE operation is performed on a TaxDocument whose sunatStatus is set
     * (isProcessed() returns true).
     * Expect: ServiceException and removeProcessor is never called.
     */
    public function testDeleteProcessedDocumentThrowsServiceException(): void
    {
        $processedDoc = new TaxDocument();
        $processedDoc->setSunatStatus('0'); // any non-null value → isProcessed() = true

        // We need to initialise required non-nullable fields to avoid errors on
        // any pre-check code paths (none in the delete branch, but safer practice).

        $this->removeProcessor->expects(self::never())->method('process');

        $processor = $this->buildProcessor();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No se puede eliminar un documento procesado.');

        $processor->process($processedDoc, new Delete(), [], []);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a related TaxDocument (the invoice being credited).
     *
     * @param bool   $isVoided Whether the related document should be flagged as voided.
     * @param string $series   The series assigned to the related tax document.
     */
    private function buildRelatedTaxDocument(bool $isVoided, string $series): TaxDocument
    {
        $doc = new TaxDocument();
        $doc->setDocumentType('01');
        $doc->setSeries($series);
        $doc->setNumber('00001');
        $doc->setTotal('118.00');
        $branch = new Branch();
        new \ReflectionProperty(Branch::class, 'id')->setValue($branch, 'branch-001');
        $doc->setBranch($branch);

        // Add at least one line so prepareCreditNoteLines() doesn't throw.
        $line = new TaxDocumentLine();
        $line->setLineNumber(1);
        $line->setProductName('Test product');
        $line->setQuantity('1.000000');
        $line->setUnitPrice('100.000000');
        $line->setTaxType('10');
        $doc->addLine($line);

        if ($isVoided) {
            $doc->setVoidTicket('T-001-1');
        }

        return $doc;
    }

    /**
     * Build a new (unsaved, id=null) TaxDocument that represents an incoming
     * credit note creation request.
     *
     * @param TaxDocument $relatedTaxDocument The invoice being credited.
     * @param string      $reasonCode         SUNAT reason code (e.g. '01').
     */
    private function buildNewCreditNote(TaxDocument $relatedTaxDocument, string $reasonCode): TaxDocument
    {
        $creditNote = new TaxDocument();
        // id is null → isNew = true
        $creditNote->setRelatedTaxDocument($relatedTaxDocument);
        $creditNote->setReasonCode($reasonCode);
        $creditNote->setReasonDescription('Anulación de la operación');

        // Provide one requested line (line 1, qty 1).
        $requestLine = new TaxDocumentLine();
        $requestLine->setLineNumber(1);
        $requestLine->setProductName('Test product');
        $requestLine->setQuantity('1.000000');
        $requestLine->setUnitPrice('100.000000');
        $creditNote->addLine($requestLine);

        return $creditNote;
    }

    private function buildBranchContextStub(string $branchId): BranchContextServiceInterface
    {
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn($branchId);

        return $context;
    }

    /**
     * Construct TaxDocumentProcessor via reflection, bypassing the constructor.
     */
    private function buildProcessor(?CreditNoteReceivableAdjuster $adjuster = null): TaxDocumentProcessor
    {
        $reflection = new ReflectionClass(TaxDocumentProcessor::class);
        /** @var TaxDocumentProcessor $processor */
        $processor = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('persistProcessor')->setValue($processor, $this->persistProcessor);
        $reflection->getProperty('removeProcessor')->setValue($processor, $this->removeProcessor);
        $reflection->getProperty('branchGuard')->setValue(
            $processor,
            new OperationalBranchGuard($this->branchContextService, $this->branchRepository),
        );
        $reflection->getProperty('taxDocumentRepository')->setValue($processor, $this->taxDocumentRepository);
        $reflection->getProperty('creditNoteStockReverser')->setValue(
            $processor,
            $this->createMock(CreditNoteStockReverserInterface::class),
        );
        $reflection->getProperty('receivableAdjuster')->setValue($processor, $adjuster);
        $reflection->getProperty('security')->setValue($processor, $this->createStub(SecurityComponent::class));
        $reflection->getProperty('activity')->setValue($processor, $this->createStub(SalesActivityInterface::class));
        $tenantSettings = $this->createStub(TenantSettingsService::class);
        $tenantSettings->method('getIgv')->willReturn(18.0);
        $reflection->getProperty('creditNoteLinePreparer')->setValue(
            $processor,
            new CreditNoteLinePreparer(
                $this->taxDocumentRepository,
                $tenantSettings,
                new CreditNoteReturnSelector($this->taxDocumentRepository),
            ),
        );

        return $processor;
    }
}
