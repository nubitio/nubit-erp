<?php

namespace App\Supplier\Application;

use App\Base\BaseController;
use App\Supplier\Domain\Supplier;
use App\Supplier\Domain\SupplierCreateUseCase;
use App\Supplier\Domain\SupplierDeleteUseCase;
use App\Supplier\Domain\SupplierGetAllUseCase;
use App\Supplier\Domain\SupplierUpdateUseCase;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SupplierController extends BaseController
{
    #[Route('/supplier', name: 'supplier_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('supplier/index.html.twig');
    }

    #[Route('/supplier/get', name: 'supplier_list', methods: ['POST'])]
    public function getAll(SupplierGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/supplier/add', name: 'supplier_create', methods: ['POST'])]
    public function add(Request $request, SupplierCreateUseCase $createUseCase): Response
    {
        /**
         * @var Supplier $supplier
         */
        $supplier = $this->serializer->deserialize(json_encode($request->request->all()), Supplier::class, 'json');
        $createUseCase($supplier);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/supplier/edit/{id}', name: 'supplier_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, SupplierUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['proveedor_id' => $id]);
        /**
         * @var Supplier $supplier
         */
        $supplier = $this->serializer->deserialize(json_encode($request->request->all()), Supplier::class, 'json');
        $updateUseCase($supplier);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/supplier/delete/{id}', name: 'supplier_delete', methods: ['DELETE', 'POST'])]
    public function delete(SupplierDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
