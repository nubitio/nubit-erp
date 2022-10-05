<?php

namespace App\Api;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends BaseController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    #[Route('/api/category', name: 'api_category_get', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative("SELECT * FROM categoria where estado = 1");
        return $this->json($data);
    }
}