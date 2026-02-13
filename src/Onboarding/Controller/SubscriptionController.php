<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Service\AccountService;
use App\Onboarding\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    #[Route('/account/tenant/{tenantId}', name: 'onboarding_tenant_detail', methods: ['GET'])]
    public function tenantDetail(int $tenantId, Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $tenant = $this->findOwnedTenant($accountId, $tenantId);
        if ($tenant === null) {
            throw $this->createNotFoundException('Empresa no encontrada.');
        }

        $subscription = $this->subscriptionService->getSubscriptionForTenant($tenantId, $accountId);

        return $this->render('onboarding/account/tenant.html.twig', [
            'tenant' => $tenant,
            'subscription' => $subscription,
        ]);
    }

    #[Route('/account/tenant/{tenantId}/change-plan', name: 'onboarding_change_plan', methods: ['POST'])]
    public function changePlan(int $tenantId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('subscription_manage', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
        }

        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $tenant = $this->findOwnedTenant($accountId, $tenantId);
        if ($tenant === null) {
            throw $this->createNotFoundException('Empresa no encontrada.');
        }

        $newPlan = mb_strtolower(trim((string) $request->request->get('plan', '')));
        $gateway = mb_strtolower(trim((string) $request->request->get('gateway', 'paypal')));
        $billingCycleRaw = mb_strtolower(trim((string) $request->request->get('billing_cycle', 'monthly')));
        $billingCycle = BillingCycle::tryFrom($billingCycleRaw) ?? BillingCycle::Monthly;

        if ($newPlan === '' || $newPlan === 'free') {
            $this->subscriptionService->cancelSubscription($tenantId);
            $this->addFlash('success', 'Plan cambiado a Free.');

            return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
        }

        try {
            $result = $this->subscriptionService->changePlan(
                $tenantId,
                $accountId,
                $newPlan,
                $billingCycle,
                $gateway,
                $this->generateUrl('onboarding_subscription_return', [
                    'tenantId' => $tenantId,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('onboarding_subscription_cancel', [
                    'tenantId' => $tenantId,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            );

            return $this->redirect($result['approval_url']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
        }
    }

    #[Route('/account/tenant/{tenantId}/change-gateway', name: 'onboarding_change_gateway', methods: ['POST'])]
    public function changeGateway(int $tenantId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('subscription_manage', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
        }

        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $tenant = $this->findOwnedTenant($accountId, $tenantId);
        if ($tenant === null) {
            throw $this->createNotFoundException('Empresa no encontrada.');
        }

        $newGateway = mb_strtolower(trim((string) $request->request->get('gateway', '')));

        try {
            $result = $this->subscriptionService->changeGateway(
                $tenantId,
                $accountId,
                $newGateway,
                $this->generateUrl('onboarding_subscription_return', [
                    'tenantId' => $tenantId,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('onboarding_subscription_cancel', [
                    'tenantId' => $tenantId,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            );

            return $this->redirect($result['approval_url']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
        }
    }

    #[Route(
        '/account/tenant/{tenantId}/cancel-subscription',
        name: 'onboarding_cancel_subscription',
        methods: ['POST']
    )]
    public function cancelSubscription(int $tenantId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('subscription_manage', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
        }

        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $tenant = $this->findOwnedTenant($accountId, $tenantId);
        if ($tenant === null) {
            throw $this->createNotFoundException('Empresa no encontrada.');
        }

        $this->subscriptionService->cancelSubscription($tenantId);
        $this->addFlash('success', 'Suscripcion cancelada. Tu plan cambiara a Free al final del periodo.');

        return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
    }

    #[Route('/account/tenant/{tenantId}/subscription-return', name: 'onboarding_subscription_return', methods: ['GET'])]
    public function subscriptionReturn(int $tenantId, Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $this->addFlash('success', 'Pago procesado. Tu suscripcion se actualizara en breve.');

        return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
    }

    #[Route('/account/tenant/{tenantId}/subscription-cancel', name: 'onboarding_subscription_cancel', methods: ['GET'])]
    public function subscriptionCancel(int $tenantId, Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $this->addFlash('warning', 'El cambio de suscripcion fue cancelado.');

        return $this->redirectToRoute('onboarding_tenant_detail', ['tenantId' => $tenantId]);
    }

    private function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOwnedTenant(int $accountId, int $tenantId): ?array
    {
        $tenants = $this->accountService->listAccountTenants($accountId);
        foreach ($tenants as $tenant) {
            if ((int) $tenant['id'] === $tenantId) {
                return $tenant;
            }
        }

        return null;
    }
}
