<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Billing\Service\PlanManager;
use App\Efact\Audit\AuditLogger;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/api/control-plane/plans')]
final class PlanApiController extends AbstractController
{
    private const array LIMIT_KEYS = ['users', 'customers', 'products', 'invoices', 'branches', 'storage_mb'];

    public function __construct(
        private readonly PlanManager $planManager,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('', name: 'api_control_plane_plans_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(['plans' => $this->planManager->getPlans()]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', name: 'api_control_plane_plans_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $name = strtolower(trim((string) ($payload['name'] ?? '')));
            $limits = $this->extractLimits($payload);
            $isDefault = (bool) ($payload['isDefault'] ?? $payload['is_default'] ?? false);

            $this->planManager->createPlan($name, $limits, $isDefault);
            $this->auditLogger->log('plan.create', ['name' => $name, 'limits' => $limits, 'is_default' => $isDefault]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true], Response::HTTP_CREATED);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'api_control_plane_plans_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $name = strtolower(trim((string) ($payload['name'] ?? '')));
            $limits = $this->extractLimits($payload);
            $isDefault = (bool) ($payload['isDefault'] ?? $payload['is_default'] ?? false);

            $this->planManager->updatePlan($id, $name, $limits, $isDefault);
            $this->auditLogger->log('plan.update', [
                'id' => $id,
                'name' => $name,
                'limits' => $limits,
                'is_default' => $isDefault,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'api_control_plane_plans_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->planManager->deletePlan($id);
            $this->auditLogger->log('plan.delete', ['id' => $id]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, int>
     */
    private function extractLimits(array $payload): array
    {
        $limits = [];
        $source = is_array($payload['limits'] ?? null) ? $payload['limits'] : $payload;

        foreach (self::LIMIT_KEYS as $key) {
            $limits[$key] = (int) ($source[$key] ?? -1);
        }

        return $limits;
    }
}
