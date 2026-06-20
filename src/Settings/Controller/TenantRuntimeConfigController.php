<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tenant/runtime-config', name: 'api_tenant_runtime_config', methods: ['GET'])]
final class TenantRuntimeConfigController extends AbstractController
{
    public function __construct(
        private readonly TenantRuntimeConfigProvider $runtimeConfigProvider,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->runtimeConfigProvider->getConfig());
    }
}
