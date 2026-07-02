<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tenant/onboarding/complete', name: 'api_tenant_onboarding_complete', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class CompleteTenantOnboardingController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantRuntimeConfigProvider $runtimeConfigProvider,
    ) {}

    public function __invoke(): JsonResponse
    {
        if ($this->tenantContext->getTenantName() === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        $settings = $this->settingsRepository->getOrCreate();
        $settings->setOnboardingCompleted(true);
        $this->entityManager->flush();

        return $this->json([
            'runtimeConfig' => $this->runtimeConfigProvider->getConfig(),
        ]);
    }
}
