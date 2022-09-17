<?php

namespace App\Warehouse\Application;

use App\Base\BaseController;
use App\Warehouse\Domain\WarehouseGetAllUseCase;
use App\Warehouse\Domain\WarehouseCreateUseCase;
use App\Warehouse\Domain\WarehouseUpdateUseCase;
use App\Warehouse\Domain\WarehouseDeleteUseCase;
use App\Warehouse\Domain\Warehouse;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WarehouseController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/warehouse', name: 'warehouse_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('warehouse/index.html.twig');
    }

    #[Route('/warehouse/get', name: 'warehouse_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(WarehouseGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/warehouse/add', name: 'warehouse_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var Warehouse $warehouse
         */
        $warehouse = $this->serializer->deserialize(json_encode($request->request->all()), Warehouse::class, 'json');
        $createUseCase = $this->container->get(WarehouseCreateUseCase::class);
        $createUseCase($warehouse);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/warehouse/edit/{id}', name: 'warehouse_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['almacen_id' => $id]);
        /**
         * @var Warehouse $warehouse
         */
        $warehouse = $this->serializer->deserialize(json_encode($request->request->all()), Warehouse::class, 'json');
        $updateUseCase = $this->container->get(WarehouseUpdateUseCase::class);
        $updateUseCase($warehouse);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/warehouse/delete/{id}', name: 'warehouse_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(WarehouseDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
