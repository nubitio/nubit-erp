<?php

namespace App\Supplier\Application;

use App\Base\BaseController;
use App\Supplier\Domain\SupplierGetAllUseCase;
use App\Supplier\Domain\SupplierCreateUseCase;
use App\Supplier\Domain\SupplierUpdateUseCase;
use App\Supplier\Domain\SupplierDeleteUseCase;
use App\Supplier\Domain\Supplier;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SupplierController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/supplier', name: 'supplier_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('supplier/index.html.twig');
    }

    #[Route('/supplier/get', name: 'supplier_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(SupplierGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/supplier/add', name: 'supplier_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var Supplier $supplier
         */
        $supplier = $this->serializer->deserialize(json_encode($request->request->all()), Supplier::class, 'json');
        $createUseCase = $this->container->get(SupplierCreateUseCase::class);
        $createUseCase($supplier);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/supplier/edit/{id}', name: 'supplier_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['proveedor_id' => $id]);
        /**
         * @var Supplier $supplier
         */
        $supplier = $this->serializer->deserialize(json_encode($request->request->all()), Supplier::class, 'json');
        $updateUseCase = $this->container->get(SupplierUpdateUseCase::class);
        $updateUseCase($supplier);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/supplier/delete/{id}', name: 'supplier_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(SupplierDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
