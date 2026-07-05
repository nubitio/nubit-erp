<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Payment gateway return/cancel handlers for subscription changes.
 */
final class SubscriptionController extends AbstractController
{
    #[Route('/account/tenant/{tenantId}/subscription-return', name: 'onboarding_subscription_return', methods: ['GET'])]
    public function subscriptionReturn(int $tenantId, Request $request): RedirectResponse
    {
        if ($this->getSessionAccountId($request) <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        return $this->redirectToRoute('onboarding_tenant_detail', [
            'tenantId' => $tenantId,
            'payment' => 'success',
        ]);
    }

    #[Route('/account/tenant/{tenantId}/subscription-cancel', name: 'onboarding_subscription_cancel', methods: ['GET'])]
    public function subscriptionCancel(int $tenantId, Request $request): RedirectResponse
    {
        if ($this->getSessionAccountId($request) <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        return $this->redirectToRoute('onboarding_tenant_detail', [
            'tenantId' => $tenantId,
            'payment' => 'cancelled',
        ]);
    }

    private function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }
}
