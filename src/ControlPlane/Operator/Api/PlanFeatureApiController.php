<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Billing\Entity\PlanFeature;
use App\ControlPlane\Billing\Feature\FeatureCatalog;
use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use App\ControlPlane\Billing\Service\PlanManager;
use App\Efact\Audit\AuditLogger;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/control-plane/plan-features')]
final class PlanFeatureApiController extends AbstractController
{
    public function __construct(
        private readonly PlanManager $planManager,
        private readonly PlanFeatureManager $planFeatureManager,
        private readonly PlanFeatureRepositoryInterface $planFeatureRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('', name: 'api_control_plane_plan_features_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $plans = $this->planManager->getPlans();
        $matrix = [];

        foreach (FeatureCatalog::keys() as $featureKey) {
            $matrix[$featureKey] = [];
            foreach ($plans as $plan) {
                $feature = $this->planFeatureRepository->findOneByPlanAndKey($plan['name'], $featureKey);
                $matrix[$featureKey][$plan['name']] = $feature === null
                    ? ['isEnabled' => false, 'configJson' => null]
                    : $this->serializePlanFeature($feature);
            }
        }

        return $this->json([
            'plans' => $plans,
            'features' => FeatureCatalog::list(),
            'categories' => FeatureCatalog::categories(),
            'matrix' => $matrix,
        ]);
    }

    #[Route(
        '/{planName}/{featureKey}',
        name: 'api_control_plane_plan_features_update',
        requirements: ['planName' => '[a-z0-9_-]+', 'featureKey' => '[a-z_]+'],
        methods: ['PATCH'],
    )]
    public function update(string $planName, string $featureKey, Request $request): JsonResponse
    {
        if (!$this->planManager->planExists($planName)) {
            return $this->json(['error' => sprintf('Plan "%s" not found.', $planName)], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $config = $this->parseConfigJson($payload['configJson'] ?? $payload['config'] ?? null);
            $this->planFeatureManager->setFeature(
                $planName,
                $featureKey,
                (bool) ($payload['isEnabled'] ?? $payload['is_enabled'] ?? false),
                $config,
            );

            $this->auditLogger->log('plan.feature.update', [
                'plan_name' => $planName,
                'feature_key' => $featureKey,
                'is_enabled' => (bool) ($payload['isEnabled'] ?? $payload['is_enabled'] ?? false),
                'config' => $config,
            ]);
        } catch (InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    /**
     * @return array{isEnabled: bool, configJson: array<string, mixed>|null}
     */
    private function serializePlanFeature(PlanFeature $feature): array
    {
        return [
            'isEnabled' => $feature->isEnabled(),
            'configJson' => $feature->getConfigJson(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseConfigJson(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
