<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\Settings\Runtime\TenantRuntimeConfigProvider;
use App\Settings\Vertical\VerticalPresetApplier;
use App\Settings\Vertical\VerticalPresetRegistry;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Applies a vertical onboarding preset to the current tenant and returns the
 * refreshed runtime config so the SPA can re-render with the new vertical.
 */
#[Route('/api/tenant/apply-preset', name: 'api_tenant_apply_preset', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
final class ApplyVerticalPresetController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly VerticalPresetRegistry $registry,
        private readonly VerticalPresetApplier $applier,
        private readonly TenantRuntimeConfigProvider $runtimeConfigProvider,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if ($this->tenantContext->getTenantName() === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = [];
        }

        $key = $payload['preset'] ?? $payload['vertical'] ?? null;
        if (!is_string($key) || $key === '') {
            return $this->json(
                ['error' => 'Missing "preset" (vertical key).'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!$this->registry->has($key)) {
            return $this->json(
                [
                    'error' => sprintf('Unknown preset "%s".', $key),
                    'available' => $this->registry->keys(),
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $this->applier->apply($this->registry->get($key));

        return $this->json([
            'applied' => [
                'preset' => $result->presetKey,
                'version' => $result->presetVersion,
                'defaultWarehouseCreated' => $result->defaultWarehouseCreated,
                'notes' => $result->notes,
            ],
            'runtimeConfig' => $this->runtimeConfigProvider->getConfig(),
        ]);
    }
}
