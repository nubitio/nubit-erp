<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sale;
use DateTime;
use App\Billing\Domain\NoteCreateUseCase;
use App\Billing\Domain\NotePdfUseCase;
use App\Billing\Domain\NoteSendUseCase;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
use App\Entity\CreditNote;
use App\Repository\CreditNoteRepository;
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
        private CreditNoteRepository $creditNoteRepository,
        private NoteSendUseCase $noteSendUseCase,
        private NoteCreateUseCase $noteCreateUseCase,
        private NotePdfUseCase $notePdfUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private TaxDocumentSyncService $taxDocumentSyncService,
    ) {
    }

    /**
     * Send credit note to SUNAT
     *
     * @throws ServiceException
     * @throws Exception
     * @throws FilesystemException
     */
    public function sendToSunat(CreditNote $creditNote): DocumentResponse
    {
        // Validate credit note before sending
        $errors = $this->validate($creditNote);
        if ($errors !== []) {
            throw new ServiceException('Validation errors: ' . implode(', ', $errors));
        }

        // Check if already sent
        if ($creditNote->isSentToSunat()) {
            throw new ServiceException('La nota de crédito ya fue enviada a SUNAT');
        }

        // Send to SUNAT using an existing billing service
        $response = $this->noteSendUseCase->__invoke($creditNote->getId());

        // Update credit note with SUNAT response
        $creditNote->setDigest($response->hash ?? null);

        // Save XML file
        if (isset($response->xml) && ($response->xml !== '' && $response->xml !== '0')) {
            $companyId = $this->companyContext->getId();
            $this->fileManager->write(
                $companyId . '/' . $response->name . '.xml',
                $response->xml
            );
        }

        // Process response
        if ($response->sunatResponse->isSuccess()) {
            $creditNote->setEnvioSunat($response->sunatResponse->getCdrResponse()?->getCode() ?? 'aceptado');
            $creditNote->setMensajeSunat($response->sunatResponse->getCdrResponse()?->getDescription() ?? 'Aceptado');

            // Save CDR (Constancia de Recepción)
            if (!in_array($response->sunatResponse->getCdrZip(), [null, '', '0'], true)) {
                $cdr = base64_decode($response->sunatResponse->getCdrZip());
                $companyId = $this->companyContext->getId();
                $this->fileManager->write(
                    $companyId . '/R-' . $response->name . '.zip',
                    $cdr
                );
            }
        } else {
            $creditNote->setEnvioSunat($response->sunatResponse->getError()?->getCode() ?? 'rechazado');
            $creditNote->setMensajeSunat($response->sunatResponse->getError()?->getMessage() ?? 'Error desconocido');
        }

        $this->entityManager->flush();
        $this->taxDocumentSyncService->recordSunatEvent(
            documentType: $creditNote->getTipoId(),
            series: $creditNote->getSerie(),
            number: strval($creditNote->getCorrelativo()),
            eventType: 'sunat_send',
            statusCode: $creditNote->getEnvioSunat(),
            statusMessage: $creditNote->getMensajeSunat(),
            digest: $creditNote->getDigest(),
            payload: [
                'name' => $response->name,
            ],
        );

        return $response;
    }

    /**
     * Generate PDF for credit note
     *
     * @throws ServiceException
     */
    public function generatePdf(CreditNote $creditNote): string
    {
        $fileResponse = $this->notePdfUseCase->__invoke($creditNote->getId());

        return $fileResponse->file;
    }

    /**
     * Generate XML for credit note
     *
     * @throws ServiceException
     * @throws FilesystemException
     * @throws Exception
     */
    public function generateXml(CreditNote $creditNote): string
    {
        $note = $this->noteCreateUseCase->__invoke($creditNote->getId());

        // XML generation would be handled by Greenter library
        // For now, try to read from a saved file or generate
        $companyId = $this->companyContext->getId();
        $xmlPath = $companyId . '/' . $note->getName() . '.xml';

        if ($this->fileManager->exists($xmlPath)) {
            return $this->fileManager->read($xmlPath);
        }

        throw new ServiceException('XML no disponible. Envíe primero la nota de crédito a SUNAT.');
    }

    /**
     * Get next correlativo for a serie
     */
    public function getNextCorrelativo(string $serie): int
    {
        return $this->creditNoteRepository->getNextCorrelativo($serie);
    }

    /**
     * Create a credit note from sale
     */
    public function createFromSale(?Sale $sale, array $data): CreditNote
    {
        $creditNote = new CreditNote();
        $creditNote->setSale($sale);
        $creditNote->setFecha($data['fecha'] ?? new DateTime());
        $creditNote->setTipoId($data['tipo_id'] ?? '07'); // 07 = Nota de crédito
        $creditNote->setCodigoMotivo($data['codigo_motivo'] ?? '01');
        $creditNote->setDescripcionMotivo($data['descripcion_motivo'] ?? '');
        $creditNote->setSerie($data['serie'] ?? 'NC01');
        $creditNote->setCorrelativo($this->getNextCorrelativo($data['serie'] ?? 'NC01'));
        $creditNote->setTotal($data['total'] ?? '0.00');
        $creditNote->setEnvioSunat('pendiente');

        $this->creditNoteRepository->save($creditNote, true);
        $this->taxDocumentSyncService->syncCreditNote($creditNote);

        return $creditNote;
    }

    /**
     * Validate credit note before sending to SUNAT
     */
    public function validate(CreditNote $creditNote): array
    {
        $errors = [];

        if ($creditNote->getSerie() === '' || $creditNote->getSerie() === '0') {
            $errors[] = 'La serie es requerida';
        }

        if ($creditNote->getCodigoMotivo() === '' || $creditNote->getCodigoMotivo() === '0') {
            $errors[] = 'El código de motivo es requerido';
        }

        if ($creditNote->getDescripcionMotivo() === '' || $creditNote->getDescripcionMotivo() === '0') {
            $errors[] = 'La descripción del motivo es requerida';
        }

        return $errors;
    }
}
