<?php

namespace App\Api;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SaleController extends BaseController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    #[Route('/api/sale/detail/{id}', name: 'api_sale_detail_get', methods: ['GET'])]
    public function index($id): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM vw_movimiento_detalle where movimiento_id = ?', [$id]);
        return $this->json($data);
    }
}