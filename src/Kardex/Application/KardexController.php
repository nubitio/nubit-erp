<?php

namespace App\Kardex\Application;

use App\Base\BaseController;
use App\Kardex\Domain\KardexGetAllUseCase;
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
        $localId = $request->getSession()->get('local_id');
        $warehouseId = $request->request->get('warehouse_id');
        $categoryId = $request->request->get('category_id');
        $dateFrom = $request->request->get('date_from');
        $dateTo = $request->request->get('date_to');

        $kardex = $kardexGetAllUseCase->__invoke($localId, $warehouseId, $categoryId, $dateFrom, $dateTo);

        return $this->json($kardex);
    }
}