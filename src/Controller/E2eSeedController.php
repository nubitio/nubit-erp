<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\E2eTestSeedServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP façade for the E2E seed/reset commands.
 *
 * Protected by the E2E_SEED_ENABLED=1 environment variable.
 * Must NEVER be exposed in production (env var is not set there).
 *
 * POST /api/test/e2e/seed  → seeds test user + data, returns credentials
 * POST /api/test/e2e/reset → cleans up seeded data
 */
#[Route('/api/test/e2e', name: 'e2e_test_')]
class E2eSeedController extends AbstractController
{
    public function __construct(
        private readonly E2eTestSeedServiceInterface $seedService,
    ) {
    }

    #[Route('/seed', name: 'seed', methods: ['POST'])]
    public function seed(): JsonResponse
    {
        if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') !== '1') {
            return $this->json(['error' => 'E2E_SEED_ENABLED is not set.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->seedService->seed();

        return $this->json($payload);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(): JsonResponse
    {
        if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') !== '1') {
            return $this->json(['error' => 'E2E_SEED_ENABLED is not set.'], Response::HTTP_FORBIDDEN);
        }

        $this->seedService->reset();

        return $this->json(['status' => 'ok']);
    }
}
