<?php

declare(strict_types=1);

namespace App\Controller;

use App\Setup\Service\E2eTestSeedServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * HTTP façade for the E2E seed/reset commands.
 *
 * Routes are registered ONLY in the `dev` and `test` environments via
 * config/routes/e2e.yaml (when@dev / when@test blocks).  In `prod` the
 * routes simply do not exist and every request returns 404.
 *
 * A secondary defence-in-depth guard (E2E_SEED_ENABLED=1) is kept inside
 * each action so that the endpoint is inert even if something misconfigures
 * the routing in a non-production environment.
 *
 * POST /api/test/e2e/seed  → seeds test user + data, returns credentials
 * POST /api/test/e2e/reset → cleans up seeded data
 */
class E2eSeedController extends AbstractController
{
    public function __construct(
        private readonly E2eTestSeedServiceInterface $seedService,
    ) {
    }

    public function seed(): JsonResponse
    {
        if (!\in_array($this->getParameter('kernel.environment'), ['dev', 'test'], true)) {
            throw new NotFoundHttpException();
        }

        if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') !== '1') {
            return $this->json(['error' => 'E2E_SEED_ENABLED is not set.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->seedService->seed();

        return $this->json($payload);
    }

    public function reset(): JsonResponse
    {
        if (!\in_array($this->getParameter('kernel.environment'), ['dev', 'test'], true)) {
            throw new NotFoundHttpException();
        }

        if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') !== '1') {
            return $this->json(['error' => 'E2E_SEED_ENABLED is not set.'], Response::HTTP_FORBIDDEN);
        }

        $this->seedService->reset();

        return $this->json(['status' => 'ok']);
    }
}
