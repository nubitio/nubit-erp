<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Api;

use App\ControlPlane\Onboarding\Service\AccountService;
use App\ControlPlane\Subscription\Payment\BillingCycle;
use App\ControlPlane\Subscription\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/account/tenants')]
final class AccountTenantApiController extends AbstractController
{
    use AccountSessionTrait;

    public function __construct(
        private readonly AccountService $accountService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    #[Route('/{tenantId}', name: 'api_account_tenant_detail', methods: ['GET'], requirements: ['tenantId' => '\d+'])]
    public function detail(int $tenantId, Request $request): JsonResponse
    {
        $tenant = $this->requireOwnedTenant($request, $tenantId);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        return $this->json([
            'tenant' => $tenant,
            'subscription' => $this->subscriptionService->getSubscriptionForTenant(
                $tenantId,
                $this->getSessionAccountId($request),
            ),
        ]);
    }

    #[Route('/{tenantId}/change-plan', name: 'api_account_change_plan', methods: ['POST'], requirements: ['tenantId' => '\d+'])]
    public function changePlan(int $tenantId, Request $request): JsonResponse
    {
        $tenant = $this->requireOwnedTenant($request, $tenantId);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $payload = $this->decodeJson($request);
        $newPlan = mb_strtolower(trim((string) ($payload['plan'] ?? '')));
        $gateway = mb_strtolower(trim((string) ($payload['gateway'] ?? 'paypal')));
        $billingCycleRaw = mb_strtolower(trim((string) ($payload['billingCycle'] ?? 'monthly')));
        $billingCycle = BillingCycle::tryFrom($billingCycleRaw) ?? BillingCycle::Monthly;

        if ($newPlan === '' || $newPlan === 'free') {
            $this->subscriptionService->cancelSubscription($tenantId);

            return $this->json(['ok' => true, 'message' => 'Plan cambiado a Free.']);
        }

        try {
            $result = $this->subscriptionService->changePlan(
                $tenantId,
                $this->getSessionAccountId($request),
                $newPlan,
                $billingCycle,
                $gateway,
                $this->generateUrl(
                    'onboarding_subscription_return',
                    ['tenantId' => $tenantId],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                $this->generateUrl(
                    'onboarding_subscription_cancel',
                    ['tenantId' => $tenantId],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            );

            return $this->json(['ok' => true, 'approvalUrl' => $result['approval_url']]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{tenantId}/change-gateway', name: 'api_account_change_gateway', methods: ['POST'], requirements: ['tenantId' => '\d+'])]
    public function changeGateway(int $tenantId, Request $request): JsonResponse
    {
        $tenant = $this->requireOwnedTenant($request, $tenantId);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $payload = $this->decodeJson($request);
        $newGateway = mb_strtolower(trim((string) ($payload['gateway'] ?? '')));

        try {
            $result = $this->subscriptionService->changeGateway(
                $tenantId,
                $this->getSessionAccountId($request),
                $newGateway,
                $this->generateUrl(
                    'onboarding_subscription_return',
                    ['tenantId' => $tenantId],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                $this->generateUrl(
                    'onboarding_subscription_cancel',
                    ['tenantId' => $tenantId],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            );

            return $this->json(['ok' => true, 'approvalUrl' => $result['approval_url']]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{tenantId}/cancel-subscription', name: 'api_account_cancel_subscription', methods: ['POST'], requirements: ['tenantId' => '\d+'])]
    public function cancelSubscription(int $tenantId, Request $request): JsonResponse
    {
        $tenant = $this->requireOwnedTenant($request, $tenantId);
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $this->subscriptionService->cancelSubscription($tenantId);

        return $this->json([
            'ok' => true,
            'message' => 'Suscripcion cancelada. Tu plan cambiara a Free al final del periodo.',
        ]);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function requireOwnedTenant(Request $request, int $tenantId): array|JsonResponse
    {
        $accountId = $this->requireAccountId($request);
        if ($accountId instanceof JsonResponse) {
            return $accountId;
        }

        $tenant = $this->findOwnedTenant($accountId, $tenantId);
        if ($tenant === null) {
            return $this->json(['error' => 'Empresa no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOwnedTenant(int $accountId, int $tenantId): ?array
    {
        foreach ($this->accountService->listAccountTenants($accountId) as $tenant) {
            if ((int) $tenant['id'] === $tenantId) {
                return $tenant;
            }
        }

        return null;
    }
}