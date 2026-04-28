<?php

declare(strict_types=1);

namespace App\Tests\State\TaxDocument;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Core\Security\Auth\JWTAuthenticationToken;
use App\Core\Security\Auth\JWTManager;
use App\Dto\TaxDocument\SeriesResultDto;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Organization\Repository\BranchRepository;
use App\Billing\Repository\TaxDocumentRepository;
use App\State\TaxDocument\TaxDocumentProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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
final class TaxDocumentProcessorTest extends TestCase
{
    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $persistProcessor;

    /** @var ProcessorInterface<mixed, mixed>&MockObject */
    private ProcessorInterface $removeProcessor;

    /** @var BranchContextService */
    private BranchContextService $branchContextService;

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
        $relatedDoc = $this->buildRelatedTaxDocument(
            isVoided: false,
            series: 'F001',
        );

        $creditNote = $this->buildNewCreditNote(
            relatedTaxDocument: $relatedDoc,
            reasonCode: '01',
        );

        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));

        $this->taxDocumentRepository
            ->method('getNextSequenceNumber')
            ->willReturn(1);

        $this->taxDocumentRepository
            ->method('getSalesCurrencyForSaleId')
            ->willReturn(['currency' => 'PEN', 'exchange_rate' => null]);

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->willReturnArgument(0);

        $processor = $this->buildProcessor();
        $result = $processor->process($creditNote, new Post(), [], []);

        self::assertInstanceOf(TaxDocument::class, $result);
        self::assertSame('07', $result->getDocumentType());
        self::assertSame('BC01', $result->getSeries());
        self::assertSame('1', $result->getNumber());
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
        $relatedDoc = $this->buildRelatedTaxDocument(
            isVoided: false,
            series: 'B001',
        );

        $creditNote = $this->buildNewCreditNote(
            relatedTaxDocument: $relatedDoc,
            reasonCode: '01',
        );

        // Branch's configured series expects F001 invoices but the sale has a B001 invoice.
        $this->taxDocumentRepository
            ->method('getTaxDocumentSeriesByBranch')
            ->willReturn(new SeriesResultDto(series: 'BC01', associatedSeries: 'F001'));

        $this->taxDocumentRepository
            ->method('getNextSequenceNumber')
            ->willReturn(1);

        $this->persistProcessor
            ->expects(self::never())
            ->method('process');

        $processor = $this->buildProcessor();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage(
            'La serie de la nota de crédito no coincide con la serie del documento afectado.'
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
        $relatedDoc = $this->buildRelatedTaxDocument(
            isVoided: true,
            series: 'F001',
        );

        $creditNote = $this->buildNewCreditNote(
            relatedTaxDocument: $relatedDoc,
            reasonCode: '01',
        );

        $this->persistProcessor
            ->expects(self::never())
            ->method('process');

        $processor = $this->buildProcessor();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No se puede generar nota de crédito para un comprobante anulado.');

        $processor->process($creditNote, new Post(), [], []);
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
        $processedDoc->setSunatStatus('0');   // any non-null value → isProcessed() = true

        // We need to initialise required non-nullable fields to avoid errors on
        // any pre-check code paths (none in the delete branch, but safer practice).

        $this->removeProcessor
            ->expects(self::never())
            ->method('process');

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

    /**
     * Build BranchContextService via reflection so getCurrentBranchId() returns
     * the given branch ID without touching the real JWT/Security infrastructure.
     *
     * Strategy:
     * - BranchContextService is final readonly → bypass constructor via reflection.
     * - JWTManager is final readonly → bypass constructor via reflection; inject a
     *   real non-empty secret so decode() can execute.
     * - Encode a real HS256 JWT containing the branchId so that JWTManager::decode()
     *   returns the expected payload.
     * - JWTAuthenticationToken is NOT final → use createMock(); its getCredentials()
     *   returns the encoded JWT.
     *
     * @param string $branchId The branch ID to be returned by getCurrentBranchId().
     */
    private function buildBranchContextStub(string $branchId): BranchContextService
    {
        $secret = 'test-secret-key-for-phpunit-testing';

        // Build a real JWT signed with the secret so JWTManager::decode() succeeds.
        $payload = ['branchId' => $branchId, 'exp' => time() + 3600];
        $rawJwt = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

        // Stub JWTAuthenticationToken (not final) to return our JWT string.
        /** @var JWTAuthenticationToken&MockObject $tokenMock */
        $tokenMock = $this->createStub(JWTAuthenticationToken::class);
        $tokenMock->method('getCredentials')->willReturn($rawJwt);

        // Stub SecurityComponent to return the token stub.
        /** @var SecurityComponent&MockObject $securityMock */
        $securityMock = $this->createStub(SecurityComponent::class);
        $securityMock->method('getToken')->willReturn($tokenMock);

        // Build JWTManager via reflection (final readonly — cannot mock directly).
        $jwtManagerReflection = new ReflectionClass(JWTManager::class);
        /** @var JWTManager $jwtManagerStub */
        $jwtManagerStub = $jwtManagerReflection->newInstanceWithoutConstructor();
        $jwtManagerReflection->getProperty('secret')->setValue($jwtManagerStub, $secret);
        $jwtManagerReflection->getProperty('logger')->setValue(
            $jwtManagerStub,
            new NullLogger()
        );

        // Build BranchContextService via reflection (final readonly).
        $branchContextReflection = new ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $stub */
        $stub = $branchContextReflection->newInstanceWithoutConstructor();
        $branchContextReflection->getProperty('security')->setValue($stub, $securityMock);
        $branchContextReflection->getProperty('jwtManager')->setValue($stub, $jwtManagerStub);

        return $stub;
    }

    /**
     * Construct TaxDocumentProcessor via reflection, bypassing the constructor.
     */
    private function buildProcessor(): TaxDocumentProcessor
    {
        $reflection = new ReflectionClass(TaxDocumentProcessor::class);
        /** @var TaxDocumentProcessor $processor */
        $processor = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('persistProcessor')->setValue($processor, $this->persistProcessor);
        $reflection->getProperty('removeProcessor')->setValue($processor, $this->removeProcessor);
        $reflection->getProperty('branchContextService')->setValue($processor, $this->branchContextService);
        $reflection->getProperty('branchRepository')->setValue($processor, $this->branchRepository);
        $reflection->getProperty('taxDocumentRepository')->setValue($processor, $this->taxDocumentRepository);

        return $processor;
    }
}
