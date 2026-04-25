<?php

declare(strict_types=1);

namespace App\EInvoicing\Invoice\Domain;

use App\Core\Audit\AuditLogger;
use App\Efact\Service\CompanyContext;
use App\EInvoicing\Billing\Domain\BillSendUseCase;
use App\EInvoicing\Billing\Domain\BillConsultUseCase;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Efact\Config\EfactConstants as Constants;
use App\Entity\TaxDocument;
use App\Repository\TaxDocumentEventRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\FilesystemException;

readonly class InvoiceSendUseCase
{
    public function __construct(
        private BillSendUseCase $billSendUseCase,
        private BillConsultUseCase $billConsultUseCase,
        private SunatSendControlService $sunatSendControlService,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private TaxDocumentEventRepository $taxDocumentEventRepository,
        private ManagerRegistry $doctrine,
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
            $taxDocument = $this->doctrine->getManager()->find(TaxDocument::class, $taxDocumentId);

            if ($taxDocument === null) {
                throw new ServiceException('Invoice not found');
            }

            $saleId = (string)($taxDocument->getCanonicalSaleId() ?? '');
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
                $taxDocument->setCdrPath($docDir . '/R-' . $response->name . '.zip');
                $recoveredByConsult = false;
                $retryable = false;
            } else {
                $errorCode = $response->sunatResponse->getError()?->getCode();
                $errorMessage = $response->sunatResponse->getError()?->getMessage();
                $retryable = !$this->sunatSendControlService->isSunatRejection($errorCode, (string)$errorMessage);
                $recoveredByConsult = false;

                if ($retryable) {
                    try {
                        $consultResponse = $this->billConsultUseCase->__invoke($taxDocumentId);
                        if ($consultResponse->isSuccess() && $consultResponse->getCdrResponse()?->isAccepted()) {
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
                            $recoveredByConsult = true;
                        } else {
                            $sunatStatus = 'pendiente';
                            $sunatMessage = $errorMessage;
                        }
                    } catch (\Throwable) {
                        $sunatStatus = 'pendiente';
                        $sunatMessage = $errorMessage;
                    }
                } else {
                    $sunatStatus = $errorCode;
                    $sunatMessage = $errorMessage;
                }
            }

            $taxDocument->setSunatStatus($sunatStatus);
            $taxDocument->setSunatMessage($sunatMessage);
            $taxDocument->setDigest($response->hash);
            $taxDocument->setUpdatedAt(new \DateTime());
            $this->doctrine->getManager()->flush();

            $this->taxDocumentEventRepository->recordEvent(
                taxDocument: $taxDocument,
                eventType: 'sunat_send',
                statusCode: $sunatStatus,
                statusMessage: $sunatMessage,
                payload: [
                    'name' => $response->name,
                    'retryable' => $retryable,
                    'recovered_by_consult' => $recoveredByConsult,
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
