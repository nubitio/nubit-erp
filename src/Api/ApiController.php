<?php

namespace App\Api;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends BaseController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Route('/api/company', name: 'api_company_get', methods: ['GET'])]
    public function getCompany(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM empresa where estado = 1');
        return $this->json($data);
    }

    #[Route('/api/local/{id}/warehouse', name: 'api_warehouse_get', methods: ['GET'])]
    public function getWarehouse($id): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative("SELECT * FROM almacen where local_id = ? and estado = 1", [$id]);
        return $this->json($data);
    }

    #[Route('/api/customer', name: 'api_customer_get', methods: ['GET'])]
    public function getCustumer(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative("SELECT * FROM cliente where estado = 1");
        return $this->json($data);
    }

    #[Route('/api/payment-method', name: 'api_payment_method_get', methods: ['GET'])]
    public function getPaymentMethod(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative("SELECT * FROM payment_type where active = 1");
        return $this->json($data);
    }

    #[Route('/api/warehouse/{id}/product', name: 'api_product_get', methods: ['GET'])]
    public function getProduct($id): JsonResponse
    {
        //$data = $this->connection->fetchAllAssociative("SELECT * FROM producto where almacen_id = ? and estado = 1", [$id]);
        $data = $this->connection->fetchAllAssociative("SELECT * FROM producto where estado = 1");
        return $this->json($data);
    }
}