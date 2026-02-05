<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Domain;

use App\EInvoicing\Billing\Domain\BillSendUseCase;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
use App\Repository\TaxDocumentRepository;
use App\Repository\TaxDocumentEventRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;

readonly class InvoiceSendUseCase
{
    public function __construct(
        private BillSendUseCase $billSendUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private TaxDocumentEventRepository $taxDocumentEventRepository,
        private TaxDocumentRepository $taxDocumentRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ServiceException
     * @throws Exception
     * @throws \Throwable
     */
    public function __invoke(string $taxDocumentId): DocumentResponse
    {
        $taxDocument = $this->taxDocumentRepository->find($taxDocumentId);

        if ($taxDocument === null) {
            throw new ServiceException('Invoice not found');
        }

        $response = $this->billSendUseCase->__invoke($taxDocument->getSale()->getId());
        $companyId = $this->companyContext->getId();
        $this->fileManager->write(
            $companyId . '/' . $response->name . '.xml',
            $response->xml
        );

        if ($response->sunatResponse->isSuccess()) {
            $sunatStatus = $response->sunatResponse->getCdrResponse()?->getCode();
            $sunatMessage = $response->sunatResponse->getCdrResponse()?->getDescription();
            $cdr = base64_decode($response->sunatResponse->getCdrZip() ?? '');
            $this->fileManager->write(
                $companyId . '/R-' . $response->name . '.zip',
                $cdr
            );
        } else {
            $sunatStatus = $response->sunatResponse->getError()?->getCode();
            $sunatMessage = $response->sunatResponse->getError()?->getMessage();
        }

        $taxDocument->setSunatStatus($sunatStatus);
        $taxDocument->setSunatMessage($sunatMessage);
        $taxDocument->setDigest($response->hash);
        $taxDocument->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->taxDocumentEventRepository->recordEvent(
            taxDocument: $taxDocument,
            eventType: 'sunat_send',
            statusCode: $sunatStatus,
            statusMessage: $sunatMessage,
            payload: [
                'name' => $response->name,
            ],
        );

        return $response;
    }
}
