<?php

namespace App\Api\System;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends BaseController
{
    #[Route('/api/settings', name: 'api_settings', methods: ['GET'])]
    #[Route('/api/config', name: 'api_settings', methods: ['GET'])]
    public function getSettings(Connection $connection): JsonResponse
    {
        $settings = $connection->fetchAllAssociative('SELECT * FROM options');

        return $this->json($settings);
    }
}