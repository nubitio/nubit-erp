<?php

namespace App\CreditNote\Application;

use App\Base\BaseController;
use App\Core\Domain\Common\ApiResponse;
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
        $result = $sendCreditNoteUseCase($id);

        if ($result->sunatResponse->isSuccess()) {
            return $this->json(
                new ApiResponse(true, $result->sunatResponse->getCdrResponse()?->getDescription() ?: '')
            );
        }

        return $this->json(new ApiResponse(false, $result->sunatResponse->getError()?->getMessage() ?: ''));
    }
}
