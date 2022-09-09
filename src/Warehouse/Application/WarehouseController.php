<?php

namespace App\Warehouse\Application;

use App\Controller\BaseController;
use App\Warehouse\Domain\WarehouseGetAllUseCase;
use App\Warehouse\Domain\WarehouseCreateUseCase;
use App\Warehouse\Domain\WarehouseUpdateUseCase;
use App\Warehouse\Domain\WarehouseDeleteUseCase;
use App\Warehouse\Domain\Warehouse;
use App\Util\Constants;

class WarehouseController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->render('warehouse/index.html.twig');
    }

    public function getAll()
    {
        $getAllUseCase = $this->container->get(WarehouseGetAllUseCase::class);
        $this->json($getAllUseCase());
    }

    public function add()
    {
        /**
         * @var Warehouse $warehouse
         */
        $warehouse = $this->serializer->deserialize(json_encode($_POST), Warehouse::class, 'json');
        $createUseCase = $this->container->get(WarehouseCreateUseCase::class);
        $createUseCase($warehouse);
        $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    public function edit()
    {
        $_POST['almacen_id'] = $this->app->get('PARAMS.id');
        /**
         * @var Warehouse $warehouse
         */
        $warehouse = $this->serializer->deserialize(json_encode($_POST), Warehouse::class, 'json');
        $updateUseCase = $this->container->get(WarehouseUpdateUseCase::class);
        $updateUseCase($warehouse);
        $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $deleteUseCase = $this->container->get(WarehouseDeleteUseCase::class);
        $deleteUseCase($id);
        $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
