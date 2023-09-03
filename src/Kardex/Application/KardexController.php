<?php

namespace App\Kardex\Application;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\XlsExporter;
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

    #[Route('/kardex/get', name: 'kardex_get_all', methods: ['GET'])]
    public function getAll(Request $request, KardexGetAllUseCase $kardexGetAllUseCase): Response
    {
        /** @var string $branchId */
        $branchId = $request->getSession()->get('branch_id');
        $warehouseId = (string) $request->request->get('warehouse_id');
        $categoryId = (string) $request->request->get('category_id');
        $dateFrom = (string) $request->query->get('dateFrom');
        $dateTo = (string) $request->query->get('dateTo');

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
    public function getDetail(Request $request, KardexGetOneUseCase $kardexGetOneUseCase, string $productId): Response
    {
        $warehouseId = strval($request->request->get('warehouse_id'));
        $dateFrom = strval($request->query->get('dateFrom') ?? date('Y-m-1'));
        $dateTo = strval($request->query->get('dateTo') ?? date('Y-m-d'));

        if ('' === $warehouseId) {
            $warehouseId = null;
        }

        $kardex = $kardexGetOneUseCase->__invoke($warehouseId, $productId, $dateFrom, $dateTo);

        return $this->json($kardex);
    }

    #[Route('/kardex/export', name: 'kardex_export', methods: ['GET'])]
    public function export(
        Request $request,
        KardexGetAllUseCase $kardexGetAllUseCase,
        XlsExporter $xlsExporter
    ): Response {
        /** @var string $branchId */
        $branchId = $request->getSession()->get('branch_id');
        $warehouseId = (string) $request->request->get('warehouse_id');
        $categoryId = (string) $request->request->get('category_id');
        $dateFrom = (string) $request->query->get('dateFrom');
        $dateTo = (string) $request->query->get('dateTo');

        if ('' === $warehouseId) {
            $warehouseId = null;
        }

        if ('' === $categoryId) {
            $categoryId = null;
        }

        $data = $kardexGetAllUseCase->__invoke($branchId, $warehouseId, $categoryId, $dateFrom, $dateTo);

        return $xlsExporter->export($data, 'kardex');
    }

    #[Route('/kardex/{productId}/export', name: 'kardex_detail_xls', methods: ['GET'])]
    public function kardex(
        Request $request,
        KardexGetOneUseCase $kardexGetOneUseCase,
        XlsExporter $xlsExporter,
        string $productId
    ): Response {
        $warehouseId = strval($request->request->get('warehouse_id'));
        $dateFrom = strval($request->query->get('dateFrom') ?? date('Y-m-1'));
        $dateTo = strval($request->query->get('dateTo') ?? date('Y-m-d'));

        if ('' === $warehouseId) {
            $warehouseId = null;
        }

        $data = $kardexGetOneUseCase->__invoke($warehouseId, $productId, $dateFrom, $dateTo);

        return $xlsExporter->export($data, 'kardex');
    }
}
