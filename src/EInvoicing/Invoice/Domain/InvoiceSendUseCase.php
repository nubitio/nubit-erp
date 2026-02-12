<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Domain;

use App\Core\Audit\AuditLogger;
use App\EInvoicing\Billing\Domain\BillSendUseCase;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Util\Constants;
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
        private TaxDocumentEventRepository $taxDocumentEventRepository,
        private TaxDocumentRepository $taxDocumentRepository,
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
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
        try {
            $taxDocument = $this->taxDocumentRepository->find($taxDocumentId);

            if ($taxDocument === null) {
                throw new ServiceException('Invoice not found');
            }

            $saleId = (string)$taxDocument->getSale()->getId();
            $response = $this->billSendUseCase->__invoke($taxDocumentId);
            $docDir = Constants::INVOICE_DIR . '/' . $response->name;
            $this->fileManager->write(
                $docDir . '/' . $response->name . '.xml',
                $response->xml
            );

            if ($response->sunatResponse->isSuccess()) {
                $sunatStatus = $response->sunatResponse->getCdrResponse()?->getCode();
                $sunatMessage = $response->sunatResponse->getCdrResponse()?->getDescription();
                $cdr = base64_decode($response->sunatResponse->getCdrZip() ?? '');
                $this->fileManager->write(
                    $docDir . '/R-' . $response->name . '.zip',
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

            $this->auditLogger->log('invoice.send', [
                'tax_document_id' => $taxDocumentId,
                'sale_id' => $saleId,
                'success' => $response->sunatResponse->isSuccess(),
                'sunat_status' => $sunatStatus,
                'sunat_message' => $sunatMessage,
                'name' => $response->name,
            ]);

            return $response;
        } catch (\Throwable $throwable) {
            $this->auditLogger->log('invoice.send_failed', [
                'tax_document_id' => $taxDocumentId,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
