<?php

namespace App\Warehouse\Application;

use App\Base\BaseController;
use App\Util\Constants;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCreateUseCase;
use App\Warehouse\Domain\WarehouseDeleteUseCase;
use App\Warehouse\Domain\WarehouseGetAllUseCase;
use App\Warehouse\Domain\WarehouseUpdateUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WarehouseController extends BaseController
{
    #[Route('/warehouse', name: 'warehouse_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('warehouse/index.html.twig');
    }

    #[Route('/warehouse/get', name: 'warehouse_list', methods: ['POST'])]
    public function getAll(WarehouseGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/warehouse/add', name: 'warehouse_create', methods: ['POST'])]
    public function add(Request $request, WarehouseCreateUseCase $createUseCase): Response
    {
        /**
         * @var Warehouse $warehouse
         */
        $warehouse = $this->serializer->deserialize(json_encode($request->request->all()), Warehouse::class, 'json');
        $createUseCase($warehouse);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/warehouse/edit/{id}', name: 'warehouse_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, WarehouseUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['almacen_id' => $id]);
        /**
         * @var Warehouse $warehouse
         */
        $warehouse = $this->serializer->deserialize(json_encode($request->request->all()), Warehouse::class, 'json');
        $updateUseCase($warehouse);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/warehouse/delete/{id}', name: 'warehouse_delete', methods: ['DELETE', 'POST'])]
    public function delete(WarehouseDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
