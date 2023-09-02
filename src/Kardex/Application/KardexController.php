<?php

namespace App\Kardex\Application;

use App\Base\BaseController;
use App\Kardex\Domain\KardexGetAllUseCase;
use App\Kardex\Domain\KardexGetOneUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class KardexController extends BaseController
{
    #[Route('/kardex', name: 'kardex_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('kardex/index.html.twig');
    }

    #[Route('/kardex/get', name: 'kardex_get_all', methods: ['POST'])]
    public function getAll(Request $request, KardexGetAllUseCase $kardexGetAllUseCase): Response
    {
        /** @var string $branchId */
        $branchId = $request->getSession()->get('branch_id');
        $warehouseId = (string) $request->request->get('warehouse_id');
        $categoryId = (string) $request->request->get('category_id');
        $dateFrom = (string) $request->request->get('date_from');
        $dateTo = (string) $request->request->get('date_to');

        if ('' === $warehouseId) {
            $warehouseId = null;
        }

        if ('' === $categoryId) {
            $categoryId = null;
        }

        $kardex = $kardexGetAllUseCase->__invoke($branchId, $warehouseId, $categoryId, $dateFrom, $dateTo);

        return $this->json($kardex);
    }

    #[Route('/kardex/{productId}/detail', name: 'kardex_detail', methods: ['GET'])]
    public function getWarehouse(Request $request, KardexGetOneUseCase $kardexGetOneUseCase, string $productId): Response
    {
        $warehouseId = strval($request->request->get('warehouse_id'));
        $dateFrom = strval($request->request->get('date_from') ?? date('Y-m-1'));
        $dateTo = strval($request->request->get('date_to') ?? date('Y-m-d'));

        $kardex = $kardexGetOneUseCase->__invoke($warehouseId, $productId, $dateFrom, $dateTo);

        return $this->json($kardex);
    }
}
