<?php

namespace App\Service;

use App\Billing\Domain\NoteCreateUseCase;
use App\Billing\Domain\NotePdfUseCase;
use App\Billing\Domain\NoteSendUseCase;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\CreditNote;
use App\Repository\CreditNoteRepository;
use Doctrine\ORM\EntityManagerInterface;

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
        private SettingsService $settings,
    ) {
    }

    /**
     * Send credit note to SUNAT
     *
     * @throws ServiceException
     */
    public function sendToSunat(CreditNote $creditNote): DocumentResponse
    {
        // Validate credit note before sending
        $errors = $this->validate($creditNote);
        if (!empty($errors)) {
            throw new ServiceException('Validation errors: ' . implode(', ', $errors));
        }

        // Check if already sent
        if ($creditNote->isSentToSunat()) {
            throw new ServiceException('La nota de crédito ya fue enviada a SUNAT');
        }

        // Send to SUNAT using existing billing service
        $response = $this->noteSendUseCase->__invoke($creditNote->getId());

        // Update credit note with SUNAT response
        $creditNote->setDigest($response->hash ?? null);

        // Save XML file
        if (!empty($response->xml)) {
            $companyId = $this->settings->get(Constants::COMPANY_ID);
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
            if (!empty($response->sunatResponse->getCdrZip())) {
                $cdr = base64_decode($response->sunatResponse->getCdrZip());
                $companyId = $this->settings->get(Constants::COMPANY_ID);
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
        return $fileResponse->content;
    }

    /**
     * Generate XML for credit note
     *
     * @throws ServiceException
     */
    public function generateXml(CreditNote $creditNote): string
    {
        $note = $this->noteCreateUseCase->__invoke($creditNote->getId());

        // XML generation would be handled by Greenter library
        // For now, try to read from saved file or generate
        $companyId = $this->settings->get(Constants::COMPANY_ID);
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
     * Create credit note from sale
     */
    public function createFromSale($sale, array $data): CreditNote
    {
        $creditNote = new CreditNote();
        $creditNote->setSale($sale);
        $creditNote->setFecha($data['fecha'] ?? new \DateTime());
        $creditNote->setTipoId($data['tipo_id'] ?? '07'); // 07 = Nota de crédito
        $creditNote->setCodigoMotivo($data['codigo_motivo'] ?? '01');
        $creditNote->setDescripcionMotivo($data['descripcion_motivo'] ?? '');
        $creditNote->setSerie($data['serie'] ?? 'NC01');
        $creditNote->setCorrelativo($this->getNextCorrelativo($data['serie'] ?? 'NC01'));
        $creditNote->setTotal($data['total'] ?? '0.00');
        $creditNote->setSubtotal($data['subtotal'] ?? '0.00');
        $creditNote->setGravada($data['gravada'] ?? '0.00');
        $creditNote->setIgv($data['igv'] ?? '0.00');
        $creditNote->setEnvioSunat('pendiente');

        $this->creditNoteRepository->save($creditNote, true);

        return $creditNote;
    }

    /**
     * Validate credit note before sending to SUNAT
     */
    public function validate(CreditNote $creditNote): array
    {
        $errors = [];

        if (!$creditNote->getSale()) {
            $errors[] = 'La nota de crédito debe estar asociada a una venta';
        }

        if (!$creditNote->getSerie()) {
            $errors[] = 'La serie es requerida';
        }

        if (!$creditNote->getCodigoMotivo()) {
            $errors[] = 'El código de motivo es requerido';
        }

        if (!$creditNote->getDescripcionMotivo()) {
            $errors[] = 'La descripción del motivo es requerida';
        }

        if ($creditNote->getTotal() === null || $creditNote->getTotal() <= 0) {
            $errors[] = 'El total debe ser mayor a 0';
        }

        return $errors;
    }
}
