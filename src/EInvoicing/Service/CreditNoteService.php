<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\EInvoicing\Billing\Domain\NoteConsultUseCase;
use App\EInvoicing\Billing\Domain\NoteSendUseCase;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use App\Repository\TaxDocumentEventRepository;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
use App\Core\Util\Constants;
use App\Entity\TaxDocument;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemException;

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
    ) {
    }

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
            $this->fileManager->write(
                $docDir . '/' . $response->name . '.xml',
                $response->xml
            );
        }

        // Process response
        if ($response->sunatResponse->isSuccess()) {
            $creditNote->setSunatStatus($response->sunatResponse->getCdrResponse()?->getCode() ?? '');
            $creditNote->setSunatMessage($response->sunatResponse->getCdrResponse()?->getDescription() ?? '');

            // Save CDR (Constancia de Recepción)
            if (!in_array($response->sunatResponse->getCdrZip(), [null, '', '0'], true)) {
                $cdr = base64_decode($response->sunatResponse->getCdrZip());
                $this->fileManager->write(
                    $docDir . '/R-' . $response->name . '.zip',
                    $cdr
                );
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
     * Consult CDR status for a credit note
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
                $cdr = base64_decode($consultResponse->getCdrZip() ?: '');
                $companyId = $this->companyContext->getId();
                $docFullName = $companyId . '-'
                    . $creditNote->getDocumentType() . '-'
                    . $consultResponse->getCdrResponse()->getId();
                $this->fileManager->write(
                    Constants::INVOICE_DIR . '/' . $docFullName . '/R-' . $docFullName . '.zip',
                    $cdr
                );
            } else {
                $sunatStatus = $consultResponse->getError()?->getCode();
                $sunatMessage = $consultResponse->getError()?->getMessage();
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
