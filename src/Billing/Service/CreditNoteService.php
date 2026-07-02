<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentEventRepository;
use App\Billing\Sunat\Domain\NoteConsultUseCase;
use App\Billing\Sunat\Domain\NoteSendUseCase;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\CompanyContext;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Filesystem\FileManager;
use Psr\Log\LoggerInterface;

/**
 * Service for Credit Note business logic
 */
readonly class CreditNoteService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private NoteSendUseCase $noteSendUseCase,
        private NoteConsultUseCase $noteConsultUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private TaxDocumentEventRepository $taxDocumentEventRepository,
        private readonly LoggerInterface $billingLogger,
    ) {}

    /**
     * Send credit note to SUNAT
     *
     * @throws ServiceException
     * @throws Exception
     * @throws FilesystemException
     * @throws MathException
     */
    public function sendToSunat(TaxDocument $creditNote): DocumentResponse
    {
        // Send to SUNAT using an existing billing service
        $response = $this->noteSendUseCase->__invoke($creditNote->getId());

        // Update credit note with SUNAT response
        $creditNote->setDigest($response->hash ?? null);

        // Save XML file
        $docDir = Constants::INVOICE_DIR . '/' . $response->name;
        if (isset($response->xml) && ($response->xml !== '' && $response->xml !== '0')) {
            $this->fileManager->write($docDir . '/' . $response->name . '.xml', $response->xml);
        }

        // Process response
        if ($response->sunatResponse->isSuccess()) {
            $creditNote->setSunatStatus($response->sunatResponse->getCdrResponse()?->getCode() ?? '');
            $creditNote->setSunatMessage($response->sunatResponse->getCdrResponse()?->getDescription() ?? '');

            // Save CDR (Constancia de Recepción)
            $cdrZipOnSend = $response->sunatResponse->getCdrZip();
            if (!in_array($cdrZipOnSend, [null, '', '0'], true)) {
                $cdr = base64_decode($cdrZipOnSend);
                $this->fileManager->write($docDir . '/R-' . $response->name . '.zip', $cdr);
                $creditNote->setCdrPath($docDir . '/R-' . $response->name . '.zip');
            } else {
                // SUNAT did not return the CDR zip inline — attempt to fetch it via consult
                $taxDocumentId = $creditNote->getId() ?? 'unknown';
                $tenantId = $this->companyContext->getId();
                try {
                    $this->consultCdr($creditNote);
                } catch (\Throwable $e) {
                    $this->billingLogger->error('credit_note.send.failed', [
                        'exception' => $e->getMessage(),
                        'tenant_id' => $tenantId ?: 'unknown',
                        'tax_document_id' => $taxDocumentId ?: 'unknown',
                    ]);
                    throw $e;
                }
            }
        } else {
            $creditNote->setSunatStatus($response->sunatResponse->getError()?->getCode() ?? '');
            $creditNote->setSunatMessage($response->sunatResponse->getError()?->getMessage() ?? '');
        }

        $this->doctrine->getManager()->flush();
        $this->taxDocumentEventRepository->recordEvent(
            taxDocument: $creditNote,
            eventType: 'sunat_send',
            statusCode: $creditNote->getSunatStatus(),
            statusMessage: $creditNote->getSunatMessage(),
            payload: [
                'name' => $response->name,
            ],
        );

        return $response;
    }

    /**
     * Consulta el estado CDR de una nota (crédito o débito) en SUNAT
     *
     * @throws FilesystemException
     */
    public function consultCdr(TaxDocument $creditNote): StatusCdrResult
    {
        $consultResponse = $this->noteConsultUseCase->__invoke($creditNote->getId());

        if ($consultResponse->isSuccess()) {
            if ($consultResponse->getCdrResponse()?->isAccepted()) {
                $sunatStatus = $consultResponse->getCdrResponse()->getCode();
                $sunatMessage = $consultResponse->getCdrResponse()->getDescription();
            } else {
                $sunatStatus = $consultResponse->getCdrResponse()?->getCode() ?? $consultResponse
                    ->getError()
                    ?->getCode();
                $sunatMessage = $consultResponse->getCdrResponse()?->getDescription() ?? $consultResponse
                    ->getError()
                    ?->getMessage();
            }

            $cdrZip = $consultResponse->getCdrZip();
            if ($consultResponse->getCdrResponse()?->isAccepted() && !in_array($cdrZip, [null, '', '0'], true)) {
                $decoded = base64_decode($cdrZip);
                if ($decoded !== '') {
                    $companyId = $this->companyContext->getId();
                    $docFullName =
                        $companyId
                        . '-'
                        . $creditNote->getDocumentType()
                        . '-'
                        . $consultResponse->getCdrResponse()->getId();
                    $cdrPath = Constants::INVOICE_DIR . '/' . $docFullName . '/R-' . $docFullName . '.zip';
                    $this->fileManager->write($cdrPath, $decoded);
                    $creditNote->setCdrPath($cdrPath);
                }
            }

            $creditNote->setSunatStatus($sunatStatus);
            $creditNote->setSunatMessage($sunatMessage);
            $creditNote->setUpdatedAt(new \DateTime());
            $this->doctrine->getManager()->flush();

            $this->taxDocumentEventRepository->recordEvent(
                taxDocument: $creditNote,
                eventType: 'sunat_consult',
                statusCode: $sunatStatus,
                statusMessage: $sunatMessage,
            );
        }

        return $consultResponse;
    }
}
