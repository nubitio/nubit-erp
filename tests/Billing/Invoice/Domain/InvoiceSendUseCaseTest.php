<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Domain;

use App\Efact\Audit\AuditLogger;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;
use App\Efact\Service\CompanyContext;
use App\Billing\Sunat\Domain\BillConsultUseCase;
use App\Billing\Sunat\Domain\BillSendUseCase;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use App\Billing\Invoice\Domain\InvoiceSendUseCase;
use App\Billing\Invoice\Domain\SunatSendControlService;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\Error;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class InvoiceSendUseCaseTest extends TestCase
{
    private BillSendUseCase&MockObject $billSendUseCase;
    private BillConsultUseCase&MockObject $billConsultUseCase;
    private SunatSendControlService&MockObject $sunatSendControlService;
    private FilesystemOperator&MockObject $filesystemOperator;
    private CompanyContext&MockObject $companyContext;
    private TaxDocumentEventRepository $taxDocumentEventRepository;
    private ManagerRegistry&MockObject $doctrine;
    private AuditLogger&MockObject $auditLogger;
    private EntityManagerInterface&MockObject $entityManager;
    private InvoiceSendUseCase $useCase;
    private FileManager $fileManager;

    protected function setUp(): void
    {
        $this->billSendUseCase           = $this->createMock(BillSendUseCase::class);
        $this->billConsultUseCase        = $this->createMock(BillConsultUseCase::class);
        $this->sunatSendControlService   = $this->createMock(SunatSendControlService::class);
        $this->filesystemOperator        = $this->createMock(FilesystemOperator::class);
        $this->companyContext             = $this->createMock(CompanyContext::class);
        $this->doctrine                  = $this->createMock(ManagerRegistry::class);
        $this->auditLogger               = $this->createMock(AuditLogger::class);
        $this->entityManager             = $this->createMock(EntityManagerInterface::class);

        $this->doctrine->method('getManager')->willReturn($this->entityManager);

        // TaxDocumentEventRepository is final — bypass constructor via reflection
        /** @var TaxDocumentEventRepository $taxDocEventRepo */
        $taxDocEventRepo = (new \ReflectionClass(TaxDocumentEventRepository::class))->newInstanceWithoutConstructor();
        $mockObjectManager = $this->createStub(ObjectManager::class);
        $mockObjectManager->method('persist'); // no-op
        $mockRegistryForRepo = $this->createStub(ManagerRegistry::class);
        $mockRegistryForRepo->method('getManager')->willReturn($mockObjectManager);
        $registryProp = (new \ReflectionClass(TaxDocumentEventRepository::class))->getProperty('registry');
        $registryProp->setValue($taxDocEventRepo, $mockRegistryForRepo);
        $this->taxDocumentEventRepository = $taxDocEventRepo;

        $tenantContext = new TenantContext();
        $slugger       = $this->createStub(SluggerInterface::class);
        $this->fileManager = new FileManager($this->filesystemOperator, $tenantContext, $slugger);

        $this->useCase = new InvoiceSendUseCase(
            $this->billSendUseCase,
            $this->billConsultUseCase,
            $this->sunatSendControlService,
            $this->fileManager,
            $this->companyContext,
            $this->taxDocumentEventRepository,
            $this->doctrine,
            $this->auditLogger,
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @return TaxDocument&MockObject */
    private function makeTaxDocument(string $id = 'td-1', string $type = '01'): TaxDocument
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->createMock(TaxDocument::class);
        $taxDocument->method('getId')->willReturn($id);
        $taxDocument->method('getDocumentType')->willReturn($type);

        return $taxDocument;
    }

    private function makeSuccessResponse(string $name = 'DOC-NAME', string $code = '0'): DocumentResponse
    {
        $cdrResponse = (new CdrResponse())
            ->setCode($code)
            ->setDescription('La factura es valida')
            ->setId('F001-1');

        $billResult = (new BillResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse)
            ->setCdrZip(base64_encode('fake-cdr-zip'));

        $response                  = new DocumentResponse();
        $response->name            = $name;
        $response->xml             = '<xml/>';
        $response->hash            = 'hash123';
        $response->sunatResponse   = $billResult;

        return $response;
    }

    private function makeErrorResponse(
        string $name = 'DOC-NAME',
        string $errorCode = '500',
        string $errorMessage = 'Error',
    ): DocumentResponse {
        $billResult = (new BillResult())
            ->setSuccess(false)
            ->setError(new Error($errorCode, $errorMessage));

        $response                  = new DocumentResponse();
        $response->name            = $name;
        $response->xml             = '<xml/>';
        $response->hash            = 'hash123';
        $response->sunatResponse   = $billResult;

        return $response;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testInvokeThrowsServiceExceptionWhenTaxDocumentNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Invoice not found');

        $this->useCase->__invoke('missing-id');
    }

    public function testInvokeAuditLogsFailureAndRethrowsWhenDocumentNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $this->auditLogger
            ->expects(self::once())
            ->method('log')
            ->with('invoice.send_failed', self::arrayHasKey('error'));

        $this->expectException(ServiceException::class);

        $this->useCase->__invoke('missing-id');
    }

    public function testInvokeSuccessPathWritesXmlAndCdrAndUpdatesEntity(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $response = $this->makeSuccessResponse('20100000000-01-F001-1');
        $this->billSendUseCase->method('__invoke')->willReturn($response);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('0');
        $taxDocument->expects(self::once())->method('setSunatMessage')->with('La factura es valida');
        $taxDocument->expects(self::once())->method('setDigest')->with('hash123');
        $taxDocument->expects(self::once())->method('setUpdatedAt');

        $this->filesystemOperator->expects(self::atLeast(2))->method('write');
        // taxDocumentEventRepository is a real (non-mock) object — we cannot call expects() on it.
        // Instead we verify the audit log fires (which happens after recordEvent).
        $this->auditLogger->expects(self::once())->method('log')
            ->with('invoice.send', self::arrayHasKey('success'));

        $result = $this->useCase->__invoke('td-1');

        self::assertSame($response, $result);
    }

    public function testInvokeRetryableErrorConsultRecovers(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $errorResponse = $this->makeErrorResponse('DOC-NAME', '1032', 'ya esta informado');
        $this->billSendUseCase->method('__invoke')->willReturn($errorResponse);

        // isSunatRejection returns false → retryable
        $this->sunatSendControlService->method('isSunatRejection')->willReturn(false);

        // consult recovers with accepted CDR
        $cdrResponse   = (new CdrResponse())
            ->setCode('0')
            ->setDescription('Aceptado')
            ->setId('F001-1');
        $consultResult = (new StatusCdrResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse)
            ->setCdrZip(base64_encode('cdr-zip'));

        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);
        $this->companyContext->method('getId')->willReturn('20100000000');

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('0');

        $result = $this->useCase->__invoke('td-1');

        self::assertSame($errorResponse, $result);
    }

    public function testInvokeRetryableErrorConsultFailsSetsPendiente(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $errorResponse = $this->makeErrorResponse('DOC-NAME', '1032', 'ya esta informado');
        $this->billSendUseCase->method('__invoke')->willReturn($errorResponse);

        $this->sunatSendControlService->method('isSunatRejection')->willReturn(false);

        // consult returns not-success
        $consultResult = (new StatusCdrResult())->setSuccess(false);
        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('pendiente');

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeRetryableErrorConsultRejectedCdrUpdatesRejectedStatus(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $errorResponse = $this->makeErrorResponse('DOC-NAME', '1032', 'ya esta informado');
        $this->billSendUseCase->method('__invoke')->willReturn($errorResponse);

        $this->sunatSendControlService->method('isSunatRejection')->willReturn(false);

        $cdrResponse = (new CdrResponse())
            ->setCode('3462')
            ->setDescription('La tasa del IGV debe corresponder con una tasa vigente.');
        $consultResult = (new StatusCdrResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse);

        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('3462');
        $taxDocument->expects(self::once())->method('setSunatMessage')->with(
            'La tasa del IGV debe corresponder con una tasa vigente.'
        );

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeRetryableErrorConsultThrowsSetsPendiente(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $errorResponse = $this->makeErrorResponse('DOC-NAME', '1032', 'ya esta informado');
        $this->billSendUseCase->method('__invoke')->willReturn($errorResponse);

        $this->sunatSendControlService->method('isSunatRejection')->willReturn(false);
        $this->billConsultUseCase->method('__invoke')->willThrowException(new \RuntimeException('network error'));

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('pendiente');

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeNonRetryableErrorSetsErrorCode(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $errorResponse = $this->makeErrorResponse('DOC-NAME', '3462', 'Rechazado');
        $this->billSendUseCase->method('__invoke')->willReturn($errorResponse);

        // non-retryable
        $this->sunatSendControlService->method('isSunatRejection')->willReturn(true);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('3462');

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeExceptionFromBillSendLogsFailureAndRethrows(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $exception = new \RuntimeException('transport failure');
        $this->billSendUseCase->method('__invoke')->willThrowException($exception);

        $this->auditLogger
            ->expects(self::once())
            ->method('log')
            ->with('invoice.send_failed', self::arrayHasKey('error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('transport failure');

        $this->useCase->__invoke('td-1');
    }
}
