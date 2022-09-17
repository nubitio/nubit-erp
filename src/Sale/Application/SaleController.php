<?php

namespace App\Sale\Application;

use App\Base\BaseController;
use App\Sale\Domain\SaleGetAllUseCase;
use App\Sale\Domain\SaleCreateUseCase;
use App\Sale\Domain\SaleUpdateUseCase;
use App\Sale\Domain\SaleDeleteUseCase;
use App\Sale\Domain\Sale;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SaleController extends BaseController
{
    #[Route('/sale', name: 'sale_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('sale/index.html.twig');
    }

    #[Route('/sale/get', name: 'sale_list', methods: ['POST'])]
    public function getAll(Request $request, SaleGetAllUseCase $getAllUseCase): Response
    {
        $filter = sprintf("tipo_id = 2 and local_id = '%s'", $request->getSession()->get('local_id'));
        return $this->json($getAllUseCase($filter));
    }

    #[Route('/sale/add', name: 'sale_create', methods: ['POST'])]
    public function add(Request $request, SaleCreateUseCase $createUseCase): Response
    {
        /**
         * @var Sale $sale
         */
        $sale = $this->serializer->deserialize(json_encode($request->request->all()), Sale::class, 'json');
        $createUseCase($sale);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/sale/edit/{id}', name: 'sale_update', methods: ['POST'])]
    public function edit(Request $request, SaleUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['movimiento_id' => $id]);
        /**
         * @var Sale $sale
         */
        $sale = $this->serializer->deserialize(json_encode($request->request->all()), Sale::class, 'json');
        $updateUseCase($sale);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/sale/delete/{id}', name: 'sale_delete', methods: ['POST'])]
    public function delete(SaleDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
