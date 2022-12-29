<?php

namespace App\Api\System;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LocalController extends BaseController
{

    #[Route('/api/local', name: 'api_local', methods: ['GET'])]
    #[Route('/api/locals', name: 'api_local', methods: ['GET'])]
    public function getLocal(Connection $connection): JsonResponse
    {
        $data = $connection->fetchAllAssociative('SELECT * FROM local');

        return $this->json($data);
    }
}