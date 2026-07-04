<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Billing\Feature\TenantFeatureOverrideManager;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Tenant\Application\TenantService;
use App\Efact\Audit\AuditLogger;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/control-plane/tenants/{id}/features', requirements: ['id' => '\d+'])]
final class TenantFeatureApiController extends AbstractController
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantFeatureOverrideManager $overrideManager,
        private readonly TenantFeatureOverrideRepositoryInterface $overrideRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('', name: 'api_control_plane_tenants_features_index', methods: ['GET'])]
    public function index(int $id): JsonResponse
    {
        if ($this->tenantService->getTenant($id) === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        $overrides = array_map(
            $this->serializeOverride(...),
            $this->overrideRepository->findAllByTenant($id),
        );

        return $this->json([
            'overrides' => $overrides,
            'featureKeys' => PlanFeatureManager::FEATURE_KEYS,
        ]);
    }

    #[Route('', name: 'api_control_plane_tenants_features_create', methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $featureKey = trim((string) ($payload['featureKey'] ?? ''));
            if (!in_array($featureKey, PlanFeatureManager::FEATURE_KEYS, true)) {
                throw new InvalidArgumentException(sprintf('Invalid feature key: "%s".', $featureKey));
            }

            $this->overrideManager->setOverride(
                tenantId: $id,
                featureKey: $featureKey,
                isEnabled: (bool) ($payload['isEnabled'] ?? false),
                config: $this->parseConfig($payload['configJson'] ?? $payload['config'] ?? null),
                expiresAt: $this->parseExpiresAt($payload['expiresAt'] ?? null),
                reason: $this->nullableString($payload['reason'] ?? null),
            );

            $this->auditLogger->log('tenant.feature_override.create', [
                'tenant_id' => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
                'feature_key' => $featureKey,
            ]);
        } catch (InvalidArgumentException|\JsonException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true], Response::HTTP_CREATED);
    }

    #[Route('/{key}', name: 'api_control_plane_tenants_features_update', requirements: ['key' => '[a-z_]+'], methods: ['PATCH'])]
    public function update(int $id, string $key, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->overrideManager->setOverride(
                tenantId: $id,
                featureKey: $key,
                isEnabled: (bool) ($payload['isEnabled'] ?? false),
                config: $this->parseConfig($payload['configJson'] ?? $payload['config'] ?? null),
                expiresAt: $this->parseExpiresAt($payload['expiresAt'] ?? null),
                reason: $this->nullableString($payload['reason'] ?? null),
            );

            $this->auditLogger->log('tenant.feature_override.update', [
                'tenant_id' => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
                'feature_key' => $key,
            ]);
        } catch (\JsonException $exception) {
            return $this->json(['error' => 'Config JSON is invalid: ' . $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/{key}', name: 'api_control_plane_tenants_features_delete', requirements: ['key' => '[a-z_]+'], methods: ['DELETE'])]
    public function delete(int $id, string $key): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->overrideManager->removeOverride($id, $key);
            $this->auditLogger->log('tenant.feature_override.delete', [
                'tenant_id' => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
                'feature_key' => $key,
            ]);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOverride(TenantFeatureOverride $override): array
    {
        return [
            'id' => $override->id,
            'featureKey' => $override->featureKey,
            'isEnabled' => $override->isEnabled(),
            'configJson' => $override->getConfigJson(),
            'expiresAt' => $override->expiresAt?->format('Y-m-d H:i:s'),
            'reason' => $override->reason,
            'isActive' => $override->isActive(),
            'isExpired' => $override->isExpired(),
            'cleanedAt' => $override->cleanedAt?->format('Y-m-d H:i:s'),
            'createdAt' => $override->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseConfig(mixed $raw): ?array
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

    private function parseExpiresAt(mixed $raw): ?\DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return new \DateTimeImmutable((string) $raw);
    }

    private function nullableString(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }
}