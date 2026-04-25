<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Domain;

use App\EInvoicing\Billing\Domain\BillConsultUseCase;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\CompanyContext;
use App\Entity\TaxDocument;
use App\Repository\TaxDocumentEventRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemException;

readonly class InvoiceConsultUseCase
{
    public function __construct(
        private BillConsultUseCase $billConsultUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private TaxDocumentEventRepository $taxDocumentEventRepository,
        private ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function __invoke(string $taxDocumentId): StatusCdrResult
    {
        $taxDocument = $this->doctrine->getManager()->find(TaxDocument::class, $taxDocumentId);

        if ($taxDocument === null) {
            throw new ServiceException('Invoice not found');
        }

        $consultResponse = $this->billConsultUseCase->__invoke($taxDocument->getId());

        if ($consultResponse->isSuccess()) {
            if ($consultResponse->getCdrResponse()?->isAccepted()) {
                $sunatStatus = $consultResponse->getCdrResponse()->getCode();
                $sunatMessage = $consultResponse->getCdrResponse()->getDescription();
                $cdr = base64_decode($consultResponse->getCdrZip() ?: '');
                $companyId = $this->companyContext->getId();
                $docFullName = $companyId . '-'
                    . $taxDocument->getDocumentType() . '-'
                    . $consultResponse->getCdrResponse()->getId();
                $this->fileManager->write(
                    Constants::INVOICE_DIR . '/' . $docFullName . '/R-' . $docFullName . '.zip',
                    $cdr
                );
                $taxDocument->setCdrPath(Constants::INVOICE_DIR . '/' . $docFullName . '/R-' . $docFullName . '.zip');
            } else {
                $sunatStatus = $consultResponse->getError()?->getCode();
                $sunatMessage = $consultResponse->getError()?->getMessage();
            }

            $taxDocument->setSunatStatus($sunatStatus);
            $taxDocument->setSunatMessage($sunatMessage);
            $taxDocument->setUpdatedAt(new \DateTime());
            $this->doctrine->getManager()->flush();

            $this->taxDocumentEventRepository->recordEvent(
                taxDocument: $taxDocument,
                eventType: 'sunat_consult',
                statusCode: $sunatStatus,
                statusMessage: $sunatMessage,
            );
        }

        return $consultResponse;
    }
}
