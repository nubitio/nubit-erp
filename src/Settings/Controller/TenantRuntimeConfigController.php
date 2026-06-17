<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tenant/runtime-config', name: 'api_tenant_runtime_config', methods: ['GET'])]
final class TenantRuntimeConfigController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantRuntimeConfigProvider $runtimeConfigProvider,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        if ($this->tenantContext->getTenantName() === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->runtimeConfigProvider->getConfig());
    }
}
