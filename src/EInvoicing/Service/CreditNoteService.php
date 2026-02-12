<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\EInvoicing\Billing\Domain\NoteSendUseCase;
use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use App\Repository\TaxDocumentEventRepository;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Util\Constants;
use App\Entity\TaxDocument;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;

/**
 * Service for Credit Note business logic
 */
readonly class CreditNoteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NoteSendUseCase $noteSendUseCase,
        private FileManager $fileManager,
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

        $this->entityManager->flush();
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
}
