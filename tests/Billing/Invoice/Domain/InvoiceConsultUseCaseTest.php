<?php

declare(strict_types=1);

namespace App\Tests\Billing\Invoice\Domain;

use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;
use App\Efact\Service\CompanyContext;
use App\Billing\Sunat\Domain\BillConsultUseCase;
use App\Billing\Invoice\Domain\InvoiceConsultUseCase;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentEventRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\Error;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class InvoiceConsultUseCaseTest extends TestCase
{
    private BillConsultUseCase&MockObject $billConsultUseCase;
    private FilesystemOperator&MockObject $filesystemOperator;
    private CompanyContext&MockObject $companyContext;
    private TaxDocumentEventRepository $taxDocumentEventRepository;
    private ManagerRegistry&MockObject $doctrine;
    private ObjectManager&MockObject $entityManager;
    private InvoiceConsultUseCase $useCase;
    private FileManager $fileManager;

    protected function setUp(): void
    {
        $this->billConsultUseCase  = $this->createMock(BillConsultUseCase::class);
        $this->filesystemOperator  = $this->createMock(FilesystemOperator::class);
        $this->companyContext       = $this->createMock(CompanyContext::class);
        $this->doctrine            = $this->createMock(ManagerRegistry::class);
        $this->entityManager       = $this->createMock(ObjectManager::class);

        $this->doctrine->method('getManager')->willReturn($this->entityManager);

        // TaxDocumentEventRepository is final — bypass constructor via reflection
        /** @var TaxDocumentEventRepository $taxDocEventRepo */
        $taxDocEventRepo    = (new \ReflectionClass(TaxDocumentEventRepository::class))->newInstanceWithoutConstructor();
        $mockObjectManager  = $this->createStub(ObjectManager::class);
        $mockObjectManager->method('persist'); // no-op
        $mockRegistry = $this->createStub(ManagerRegistry::class);
        $mockRegistry->method('getManager')->willReturn($mockObjectManager);
        $registryProp = (new \ReflectionClass(TaxDocumentEventRepository::class))->getProperty('registry');
        $registryProp->setValue($taxDocEventRepo, $mockRegistry);
        $this->taxDocumentEventRepository = $taxDocEventRepo;

        $tenantContext    = new TenantContext();
        $slugger          = $this->createStub(SluggerInterface::class);
        $this->fileManager = new FileManager($this->filesystemOperator, $tenantContext, $slugger);

        $this->useCase = new InvoiceConsultUseCase(
            $this->billConsultUseCase,
            $this->fileManager,
            $this->companyContext,
            $this->taxDocumentEventRepository,
            $this->doctrine,
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

    private function makeAcceptedConsultResult(string $code = '0', string $id = 'F001-1'): StatusCdrResult
    {
        $cdrResponse = (new CdrResponse())
            ->setCode($code)
            ->setDescription('La factura es valida')
            ->setId($id);

        return (new StatusCdrResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse)
            ->setCdrZip(base64_encode('fake-cdr-zip'));
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

    public function testInvokeReturnsConsultResponseWhenNotSuccess(): void
    {
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $consultResult = (new StatusCdrResult())->setSuccess(false);
        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $result = $this->useCase->__invoke('td-1');

        self::assertSame($consultResult, $result);
    }

    public function testInvokeSuccessAcceptedWritesCdrAndUpdatesEntity(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument('td-1', '01');
        $this->entityManager->method('find')->willReturn($taxDocument);

        $this->companyContext->method('getId')->willReturn('20100000000');

        $consultResult = $this->makeAcceptedConsultResult('0', 'F001-1');
        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('0');
        $taxDocument->expects(self::once())->method('setSunatMessage')->with('La factura es valida');
        $taxDocument->expects(self::once())->method('setUpdatedAt');

        $this->filesystemOperator->expects(self::once())->method('write');

        $result = $this->useCase->__invoke('td-1');

        self::assertSame($consultResult, $result);
    }

    public function testInvokeSuccessNotAcceptedSetsErrorCodeFromError(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        // isAccepted() returns false when code is not 0 and not >=4000
        // Build a success result where cdrResponse code is rejected (e.g. 2800)
        $cdrResponse = (new CdrResponse())
            ->setCode('2800')
            ->setDescription('El comprobante ya existe');
        // Attach an error to the StatusCdrResult
        $consultResult = (new StatusCdrResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse)
            ->setError(new Error('2800', 'El comprobante ya existe'));

        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with('2800');
        $taxDocument->expects(self::once())->method('setSunatMessage')->with('El comprobante ya existe');

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeSuccessNotAcceptedSetsNullWhenNoError(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $cdrResponse = (new CdrResponse())
            ->setCode('2800')
            ->setDescription('Rechazado');
        $consultResult = (new StatusCdrResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse);
        // No error set → getError() returns null → code/message = null

        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $taxDocument->expects(self::once())->method('setSunatStatus')->with(null);
        $taxDocument->expects(self::once())->method('setSunatMessage')->with(null);

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeFlushesOnSuccess(): void
    {
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);
        $this->companyContext->method('getId')->willReturn('20100000000');

        $consultResult = $this->makeAcceptedConsultResult();
        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $this->entityManager->expects(self::once())->method('flush');

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeDoesNotFlushWhenNotSuccess(): void
    {
        $taxDocument = $this->makeTaxDocument();
        $this->entityManager->method('find')->willReturn($taxDocument);

        $consultResult = (new StatusCdrResult())->setSuccess(false);
        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        $this->entityManager->expects(self::never())->method('flush');

        $this->useCase->__invoke('td-1');
    }

    public function testInvokeAcceptedUsesCompanyIdAndDocTypeInFilePath(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->makeTaxDocument('td-2', '03');
        $this->entityManager->method('find')->willReturn($taxDocument);
        $this->companyContext->method('getId')->willReturn('20555000001');

        $cdrResponse = (new CdrResponse())
            ->setCode('0')
            ->setDescription('ok')
            ->setId('B001-5');
        $consultResult = (new StatusCdrResult())
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse)
            ->setCdrZip(base64_encode('zip-data'));

        $this->billConsultUseCase->method('__invoke')->willReturn($consultResult);

        // The written path should embed the company id, doc type, and CDR id
        $this->filesystemOperator
            ->expects(self::once())
            ->method('write')
            ->with(
                self::stringContains('20555000001-03-B001-5'),
                self::anything()
            );

        $this->useCase->__invoke('td-2');
    }
}
