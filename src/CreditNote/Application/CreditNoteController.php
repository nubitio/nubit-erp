<?php

namespace App\CreditNote\Application;

use App\Base\BaseController;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\CreditNoteCreateUseCase;
use App\CreditNote\Domain\CreditNoteDeleteUseCase;
use App\CreditNote\Domain\CreditNoteGetAllUseCase;
use App\CreditNote\Domain\CreditNoteUpdateUseCase;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class CreditNoteController extends BaseController
{
    #[Route('/credit-note', name: 'credit-note_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('credit-note/index.html.twig');
    }

    #[Route('/credit-note/get', name: 'credit-note_list', methods: ['POST'])]
    public function getAll(CreditNoteGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/credit-note/add', name: 'credit-note_create', methods: ['POST'])]
    public function add(Request $request, CreditNoteCreateUseCase $createUseCase): Response
    {
        $noteId = Uuid::v4();
        $request->request->set('nota_id', $noteId);
        /**
         * @var CreditNote $creditNote
         */
        $creditNote = $this->serializer->deserialize(json_encode($request->request->all()), CreditNote::class, 'json');
        $createUseCase($creditNote);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/credit-note/edit/{id}', name: 'credit-note_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, CreditNoteUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['nota_id' => $id]);
        /**
         * @var CreditNote $creditNote
         */
        $creditNote = $this->serializer->deserialize(json_encode($request->request->all()), CreditNote::class, 'json');
        $updateUseCase($creditNote);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/credit-note/delete/{id}', name: 'credit-note_delete', methods: ['DELETE', 'POST'])]
    public function delete(CreditNoteDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
