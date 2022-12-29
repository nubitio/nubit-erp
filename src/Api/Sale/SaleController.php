<?php

namespace App\Api\Sale;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SaleController extends BaseController
{
    #[Route('/api/sales/{localId}', name: 'api_sales', methods: ['GET'])]
    public function getSales(Connection $connection, string $localId): JsonResponse
    {
        $sales = [];

        $data = $connection->fetchAllAssociative('SELECT * FROM vw_movimiento WHERE numero > 0 and local_id = :localId', ['localId' => $localId]);

        foreach ($data as $item) {
            $detail = $connection->fetchAllAssociative('SELECT * FROM vw_movimiento_detalle WHERE movimiento_id = ?', [$item['movimiento_id']]);
            $item['detail'] = $detail;
            $sales[] = $item;
        }

        return $this->json($sales);
    }

    #[Route('/api/sale/{saleId}', name: 'api_sale', methods: ['GET'])]
    public function getSale(Connection $connection, string $saleId): JsonResponse
    {
        $sale = $connection->fetchAssociative('SELECT * FROM vw_movimiento WHERE movimiento_id = :saleId', ['saleId' => $saleId]);

        $detail = $connection->fetchAllAssociative('SELECT * FROM vw_movimiento_detalle WHERE movimiento_id = ?', [$sale['movimiento_id']]);
        $sale['detail'] = $detail;

        return $this->json($sale);
    }
}