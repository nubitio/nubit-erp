<?php

namespace App\CreditNote\Application;

use App\Base\BaseController;
use App\CreditNote\Domain\SendCreditNoteUseCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CreditNoteController extends BaseController
{
    #[Route('/credit-note', name: 'credit-note_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('credit-note/index.html.twig');
    }

    #[Route('/credit-note/send/{id}', name: 'credit-note_send', methods: ['POST'])]
    public function send(SendCreditNoteUseCase $sendCreditNoteUseCase, string $id): Response
    {
        $sendCreditNoteUseCase($id);

        return $this->json(['success' => true, 'msg' => 'Comprobante enviado correctamente']);
    }
}
