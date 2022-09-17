<?php

namespace App\SaleDetail\Application;

use App\Base\BaseController;
use App\SaleDetail\Domain\SaleDetailGetAllUseCase;
use App\SaleDetail\Domain\SaleDetailCreateUseCase;
use App\SaleDetail\Domain\SaleDetailUpdateUseCase;
use App\SaleDetail\Domain\SaleDetailDeleteUseCase;
use App\SaleDetail\Domain\SaleDetail;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SaleDetailController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/saledetail', name: 'saledetail_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('saledetail/index.html.twig');
    }

    #[Route('/saledetail/get', name: 'saledetail_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(SaleDetailGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/saledetail/add', name: 'saledetail_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var SaleDetail $saledetail
         */
        $saledetail = $this->serializer->deserialize(json_encode($request->request->all()), SaleDetail::class, 'json');
        $createUseCase = $this->container->get(SaleDetailCreateUseCase::class);
        $createUseCase($saledetail);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/saledetail/edit/{id}', name: 'saledetail_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['movimiento_id' => $id]);
        /**
         * @var SaleDetail $saledetail
         */
        $saledetail = $this->serializer->deserialize(json_encode($request->request->all()), SaleDetail::class, 'json');
        $updateUseCase = $this->container->get(SaleDetailUpdateUseCase::class);
        $updateUseCase($saledetail);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/saledetail/delete/{id}', name: 'saledetail_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(SaleDetailDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
