<?php

namespace App\Transaction\Application;

use App\Base\BaseController;
use App\Transaction\Domain\TransactionGetAllUseCase;
use App\Transaction\Domain\TransactionCreateUseCase;
use App\Transaction\Domain\TransactionUpdateUseCase;
use App\Transaction\Domain\TransactionDeleteUseCase;
use App\Transaction\Domain\Transaction;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/transaction', name: 'transaction_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('transaction/index.html.twig');
    }

    #[Route('/transaction/get', name: 'transaction_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(TransactionGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/transaction/add', name: 'transaction_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var Transaction $transaction
         */
        $transaction = $this->serializer->deserialize(json_encode($request->request->all()), Transaction::class, 'json');
        $createUseCase = $this->container->get(TransactionCreateUseCase::class);
        $createUseCase($transaction);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/transaction/edit/{id}', name: 'transaction_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['operacion_id' => $id]);
        /**
         * @var Transaction $transaction
         */
        $transaction = $this->serializer->deserialize(json_encode($request->request->all()), Transaction::class, 'json');
        $updateUseCase = $this->container->get(TransactionUpdateUseCase::class);
        $updateUseCase($transaction);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/transaction/delete/{id}', name: 'transaction_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(TransactionDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
