<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\Entity\CreditNote;
use App\Repository\CreditNoteRepository;
use App\Repository\SaleRepository;
use App\Service\CreditNoteService;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SendCreditNote extends AbstractController
{
    public function __construct(
        private readonly CreditNoteRepository $creditNoteRepository,
        private readonly SaleRepository $saleRepository,
        private readonly CreditNoteService $creditNoteService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $noteId = $request->get('id');
        $creditNote = $noteId !== '' ? $this->creditNoteRepository->find($noteId) : null;

        if (!$creditNote instanceof CreditNote && $noteId !== '') {
            $sale = $this->saleRepository->find($noteId);
            if ($sale !== null) {
                $notes = $this->creditNoteRepository->findBySale($sale);
                $creditNote = $notes[0] ?? null;
            }
        }

        if (!$creditNote instanceof CreditNote) {
            return new ApiResponse(false, 'Nota de crédito no encontrada');
        }

        try {
            $result = $this->creditNoteService->sendToSunat($creditNote)->sunatResponse;

            if ($result->isSuccess()) {
                return new ApiResponse(true, $result->getCdrResponse()?->getDescription() ?: 'Enviado exitosamente');
            }

            return new ApiResponse(false, $result->getError()?->getMessage() ?: 'Error al enviar');
        } catch (FilesystemException) {
            return new ApiResponse(false, 'Error al guardar el archivo XML');
        } catch (\Exception $e) {
            return new ApiResponse(false, $e->getMessage());
        }
    }
}
