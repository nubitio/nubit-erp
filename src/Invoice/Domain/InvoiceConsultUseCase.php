<?php

declare(strict_types=1);

namespace App\Invoice\Domain;

use App\Billing\Domain\BillConsultUseCase;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
use App\Repository\TaxDocumentRepository;
use App\Service\TaxDocumentSyncService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemException;

readonly class InvoiceConsultUseCase
{
    public function __construct(
        private BillConsultUseCase $billConsultUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private TaxDocumentSyncService $taxDocumentSyncService,
        private TaxDocumentRepository $taxDocumentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function __invoke(string $taxDocumentId): StatusCdrResult
    {
        $taxDocument = $this->taxDocumentRepository->find($taxDocumentId);

        if ($taxDocument === null) {
            throw new ServiceException('Invoice not found');
        }

        $consultResponse = $this->billConsultUseCase->__invoke($taxDocument->getSale()->getId());

        if ($consultResponse->isSuccess()) {
            if ($consultResponse->getCdrResponse()?->isAccepted()) {
                $sunatStatus = $consultResponse->getCdrResponse()->getCode();
                $sunatMessage = $consultResponse->getCdrResponse()->getDescription();
                $cdr = base64_decode($consultResponse->getCdrZip() ?: '');
                $companyId = $this->companyContext->getId();
                $name = $companyId .
                    $taxDocument['document_type'] .
                    $consultResponse->getCdrResponse()->getId() .
                    '.zip';
                $this->fileManager->write(
                    $companyId . '/R-' . $name . '.zip',
                    $cdr
                );
            } else {
                $sunatStatus = $consultResponse->getError()?->getCode();
                $sunatMessage = $consultResponse->getError()?->getMessage();
            }

            $taxDocument->setSunatStatus($sunatStatus);
            $taxDocument->setSunatMessage($sunatMessage);
            $taxDocument->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $this->taxDocumentSyncService->recordSunatEvent(
                documentType: strval($taxDocument['document_type']),
                series: strval($taxDocument['series']),
                number: strval($taxDocument['number']),
                eventType: 'sunat_consult',
                statusCode: $sunatStatus,
                statusMessage: $sunatMessage,
                digest: $taxDocument['digest'] ?? null,
            );
        }

        return $consultResponse;
    }
}
