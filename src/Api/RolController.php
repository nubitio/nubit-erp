<?php

namespace App\Api;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RolController extends BaseController
{
    private Connection $connection;

    public function __construct()
    {
        parent::__construct();
        $this->connection = $this->container->get(Connection::class);
    }

    #[Route('/api/rol', name: 'api_rol_get', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM rol');
        return $this->json($data);
    }
}