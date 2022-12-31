<?php

namespace App\Api\Customer;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CustomerController extends BaseController
{
    #[Route('/api/customers', name: 'api_customer_get_all', methods: ['GET'])]
    public function getAllCustomers(Connection $connection): JsonResponse
    {
        $customers = $connection->fetchAllAssociative('SELECT * FROM cliente where  estado = 1');
        return $this->json($customers);
    }
}