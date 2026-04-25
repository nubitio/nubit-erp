<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Service;

use App\Core\Context\TenantContext;
use App\Core\Filesystem\FileManager;
use App\Efact\Service\CompanyContext;
use App\EInvoicing\Billing\Domain\NoteConsultUseCase;
use App\EInvoicing\Billing\Domain\NoteSendUseCase;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use App\EInvoicing\Service\CreditNoteService;
use App\Entity\TaxDocument;
use App\Repository\TaxDocumentEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Greenter\Model\Response\BillResult;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * AC-4: CreditNoteService catch block logs error with required keys and re-throws.
 * AC-7: tenant_id falls back to 'unknown' when CompanyContext returns empty string.
 */
#[AllowMockObjectsWithoutExpectations]
final class CreditNoteServiceLoggingTest extends TestCase
{
    private ManagerRegistry&Stub $doctrine;
    private NoteSendUseCase&Stub $noteSendUseCase;
    private NoteConsultUseCase&Stub $noteConsultUseCase;
    private CompanyContext&Stub $companyContext;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->doctrine = $this->createStub(ManagerRegistry::class);
        $this->noteSendUseCase = $this->createStub(NoteSendUseCase::class);
        $this->noteConsultUseCase = $this->createStub(NoteConsultUseCase::class);
        $this->companyContext = $this->createStub(CompanyContext::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Wire up EntityManager stub so flush() doesn't blow up
        $em = $this->createStub(EntityManagerInterface::class);
        $this->doctrine->method('getManager')->willReturn($em);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * FileManager is final — construct it with a no-op FilesystemOperator stub,
     * following the pattern used in InvoiceVoidStrategyTest.
     */
    private function makeFileManager(): FileManager
    {
        $filesystem = $this->createStub(FilesystemOperator::class);

        return new FileManager($filesystem, new TenantContext(), new AsciiSlugger());
    }

    /**
     * TaxDocumentEventRepository is final — bypass its constructor via reflection,
     * following the pattern used in SaleVoidUseCaseTest.
     */
    private function makeTaxDocumentEventRepository(): TaxDocumentEventRepository
    {
        /** @var TaxDocumentEventRepository $repo */
        $repo = (new \ReflectionClass(TaxDocumentEventRepository::class))->newInstanceWithoutConstructor();

        $mockRegistry = $this->createStub(ManagerRegistry::class);
        $mockRegistry->method('getManager')->willReturn($this->createStub(EntityManagerInterface::class));

        $registryProp = (new \ReflectionClass(TaxDocumentEventRepository::class))->getProperty('registry');
        $registryProp->setValue($repo, $mockRegistry);

        return $repo;
    }

    private function makeService(): CreditNoteService
    {
        return new CreditNoteService(
            doctrine: $this->doctrine,
            noteSendUseCase: $this->noteSendUseCase,
            noteConsultUseCase: $this->noteConsultUseCase,
            fileManager: $this->makeFileManager(),
            companyContext: $this->companyContext,
            taxDocumentEventRepository: $this->makeTaxDocumentEventRepository(),
            billingLogger: $this->logger,
        );
    }

    /**
     * Build a DocumentResponse that triggers the consultCdr branch:
     * sunatResponse is successful but returns no CDR zip inline.
     */
    private function makeSuccessResponseWithoutCdrZip(): DocumentResponse
    {
        $sunatResponse = new BillResult();
        $sunatResponse->setSuccess(true);

        $response = new DocumentResponse();
        $response->name = 'NC-F001-00000001';
        $response->sunatResponse = $sunatResponse;

        return $response;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AC-4 tests
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * AC-4: When consultCdr throws, error is logged with keys
     *       'exception', 'tenant_id', 'tax_document_id' — and the exception is re-thrown.
     */
    public function testSendToSunatLogsErrorAndRethrowsWhenConsultCdrThrows(): void
    {
        $creditNote = $this->createStub(TaxDocument::class);
        $creditNote->method('getId')->willReturn('doc-abc-123');

        $this->companyContext->method('getId')->willReturn('tenant-xyz');

        $this->noteSendUseCase->method('__invoke')
            ->willReturn($this->makeSuccessResponseWithoutCdrZip());

        $cause = new \RuntimeException('SUNAT consult timeout');
        $this->noteConsultUseCase->method('__invoke')->willThrowException($cause);

        // Assert: logger->error() is called exactly once with the right event name and context keys
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'credit_note.send.failed',
                $this->callback(static function (array $context): bool {
                    return array_key_exists('exception', $context)
                        && array_key_exists('tenant_id', $context)
                        && array_key_exists('tax_document_id', $context);
                })
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SUNAT consult timeout');

        $this->makeService()->sendToSunat($creditNote);
    }

    /**
     * AC-4 + AC-7: When tenant_id cannot be resolved (empty string from CompanyContext),
     *              the log context still contains tenant_id = 'unknown'.
     */
    public function testSendToSunatLogsUnknownTenantWhenCompanyContextReturnsEmpty(): void
    {
        $creditNote = $this->createStub(TaxDocument::class);
        $creditNote->method('getId')->willReturn('doc-no-tenant');

        // CompanyContext returns empty string — simulates missing tenant context
        $this->companyContext->method('getId')->willReturn('');

        $this->noteSendUseCase->method('__invoke')
            ->willReturn($this->makeSuccessResponseWithoutCdrZip());

        $this->noteConsultUseCase->method('__invoke')
            ->willThrowException(new \RuntimeException('network error'));

        $capturedContext = null;
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'credit_note.send.failed',
                $this->callback(static function (array $context) use (&$capturedContext): bool {
                    $capturedContext = $context;

                    return true;
                })
            );

        try {
            $this->makeService()->sendToSunat($creditNote);
        } catch (\RuntimeException) {
            // expected re-throw
        }

        $this->assertNotNull($capturedContext, 'Logger::error must have been called');
        $this->assertSame(
            'unknown',
            $capturedContext['tenant_id'],
            'tenant_id must fall back to "unknown" when CompanyContext returns empty string'
        );
    }

    /**
     * AC-4: The exception message is captured in the 'exception' context key.
     */
    public function testSendToSunatLogsExceptionMessageInContext(): void
    {
        $creditNote = $this->createStub(TaxDocument::class);
        $creditNote->method('getId')->willReturn('doc-err-456');

        $this->companyContext->method('getId')->willReturn('tenant-abc');

        $this->noteSendUseCase->method('__invoke')
            ->willReturn($this->makeSuccessResponseWithoutCdrZip());

        $cause = new \RuntimeException('CDR fetch failed: SSL error');
        $this->noteConsultUseCase->method('__invoke')->willThrowException($cause);

        $capturedContext = null;
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'credit_note.send.failed',
                $this->callback(static function (array $context) use (&$capturedContext): bool {
                    $capturedContext = $context;

                    return true;
                })
            );

        try {
            $this->makeService()->sendToSunat($creditNote);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNotNull($capturedContext);
        $this->assertSame('CDR fetch failed: SSL error', $capturedContext['exception']);
        $this->assertSame('tenant-abc', $capturedContext['tenant_id']);
        $this->assertSame('doc-err-456', $capturedContext['tax_document_id']);
    }

    /**
     * AC-4: The original exception identity is preserved on re-throw (same instance).
     */
    public function testSendToSunatRethrowsOriginalExceptionInstance(): void
    {
        $creditNote = $this->createStub(TaxDocument::class);
        $creditNote->method('getId')->willReturn('doc-rethrow');

        $this->companyContext->method('getId')->willReturn('tenant-rethrow');

        $this->noteSendUseCase->method('__invoke')
            ->willReturn($this->makeSuccessResponseWithoutCdrZip());

        $original = new \LogicException('original exception');
        $this->noteConsultUseCase->method('__invoke')->willThrowException($original);

        $this->logger->method('error'); // allow call without expectation

        $caught = null;
        try {
            $this->makeService()->sendToSunat($creditNote);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertSame($original, $caught, 'The exact original exception instance must be re-thrown');
    }
}
