<?php

namespace App\Operation\Application;

use App\Base\BaseController;
use App\Operation\Domain\OperationGetAllUseCase;
use App\Operation\Domain\OperationCreateUseCase;
use App\Operation\Domain\OperationUpdateUseCase;
use App\Operation\Domain\OperationDeleteUseCase;
use App\Operation\Domain\Operation;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OperationController extends BaseController
{
    #[Route('/operation', name: 'operation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('operation/index.html.twig');
    }

    #[Route('/operation/get', name: 'operation_list', methods: ['POST'])]
    public function getAll(OperationGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/operation/add', name: 'operation_create', methods: ['POST'])]
    public function add(Request $request, OperationCreateUseCase $createUseCase): Response
    {
        /**
         * @var Operation $operation
         */
        $operation = $this->serializer->deserialize(json_encode($request->request->all()), Operation::class, 'json');
        $createUseCase($operation);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/operation/edit/{id}', name: 'operation_update', methods: ['POST'])]
    public function edit(Request $request, OperationUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['operacion_id' => $id]);
        /**
         * @var Operation $operation
         */
        $operation = $this->serializer->deserialize(json_encode($request->request->all()), Operation::class, 'json');
        $updateUseCase($operation);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/operation/delete/{id}', name: 'operation_delete', methods: ['POST'])]
    public function delete(OperationDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
