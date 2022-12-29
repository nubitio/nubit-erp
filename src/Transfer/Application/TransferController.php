<?php

namespace App\Transfer\Application;

use App\Base\BaseController;
use App\Transfer\Domain\Transfer;
use App\Transfer\Domain\TransferCreateUseCase;
use App\Transfer\Domain\TransferDeleteUseCase;
use App\Transfer\Domain\TransferGetAllUseCase;
use App\Transfer\Domain\TransferUpdateUseCase;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class TransferController extends BaseController
{
    #[Route('/transfer', name: 'transfer_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('transfer/index.html.twig');
    }

    #[Route('/transfer/get', name: 'transfer_list', methods: ['POST'])]
    public function getAll(TransferGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/transfer/add', name: 'transfer_create', methods: ['POST'])]
    public function add(Request $request, TransferCreateUseCase $createUseCase): Response
    {
        $transferId = Uuid::v4();
        $request->request->set('transfer_id', $transferId);
        /**
         * @var Transfer $transfer
         */
        $transfer = $this->serializer->deserialize(json_encode($request->request->all()), Transfer::class, 'json');
        $createUseCase($transfer);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/transfer/edit/{id}', name: 'transfer_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, TransferUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['transfer_id' => $id]);
        /**
         * @var Transfer $transfer
         */
        $transfer = $this->serializer->deserialize(json_encode($request->request->all()), Transfer::class, 'json');
        $updateUseCase($transfer);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/transfer/delete/{id}', name: 'transfer_delete', methods: ['DELETE', 'POST'])]
    public function delete(TransferDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
