<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Base\BaseController;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class HealthController extends BaseController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $entityManager): JsonResponse
    {
        $dbStatus = 'ok';
        $statusCode = Response::HTTP_OK;

        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
        } catch (Throwable) {
            // Keep response simple: mark degraded if database connectivity fails.
            $dbStatus = 'degraded';
            $statusCode = Response::HTTP_SERVICE_UNAVAILABLE;
        }

        return $this->json(
            [
                'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $dbStatus,
                ],
                'timestamp' => new DateTimeImmutable()->format(DATE_ATOM),
            ],
            $statusCode
        );
    }
}
