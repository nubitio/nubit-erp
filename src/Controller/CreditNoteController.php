<?php

namespace App\Controller;

use App\Core\Base\BaseController;
use App\Core\Http\ApiResponse;
use App\Entity\CreditNote;
use App\Repository\CreditNoteRepository;
use App\Service\CreditNoteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/credit-note')]
class CreditNoteController extends BaseController
{
    public function __construct(
        private readonly CreditNoteRepository $creditNoteRepository,
        private readonly CreditNoteService $creditNoteService,
    ) {
    }

    #[Route('', name: 'credit_note_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('credit-note/index.html.twig');
    }

    #[Route('/send/{id}', name: 'credit_note_send', methods: ['POST'])]
    public function send(string $id): Response
    {
        $creditNote = $this->creditNoteRepository->find($id);

        if (!$creditNote) {
            return $this->json(
                new ApiResponse(false, 'Nota de crédito no encontrada'),
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $result = $this->creditNoteService->sendToSunat($creditNote);

            if ($result->isSuccess()) {
                return $this->json(
                    new ApiResponse(true, $result->getCdrResponse()?->getDescription() ?: 'Enviado exitosamente')
                );
            }

            return $this->json(
                new ApiResponse(false, $result->getError()?->getMessage() ?: 'Error al enviar'),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return $this->json(
                new ApiResponse(false, $e->getMessage()),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}/pdf', name: 'credit_note_pdf', methods: ['GET'])]
    public function downloadPdf(string $id): Response
    {
        $creditNote = $this->creditNoteRepository->find($id);

        if (!$creditNote) {
            throw $this->createNotFoundException('Nota de crédito no encontrada');
        }

        $pdf = $this->creditNoteService->generatePdf($creditNote);

        return new Response(
            $pdf,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'attachment; filename="nota-credito-%s.pdf"',
                    $creditNote->getDocumentNumber()
                ),
            ]
        );
    }

    #[Route('/{id}/xml', name: 'credit_note_xml', methods: ['GET'])]
    public function downloadXml(string $id): Response
    {
        $creditNote = $this->creditNoteRepository->find($id);

        if (!$creditNote) {
            throw $this->createNotFoundException('Nota de crédito no encontrada');
        }

        $xml = $this->creditNoteService->generateXml($creditNote);

        return new Response(
            $xml,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => sprintf(
                    'attachment; filename="nota-credito-%s.xml"',
                    $creditNote->getDocumentNumber()
                ),
            ]
        );
    }
}
