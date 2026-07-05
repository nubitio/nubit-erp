<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Payment gateway return/cancel handlers for tenant provisioning.
 */
final class TenantWizardController extends AbstractController
{
    #[Route('/register/payment-return/{token}', name: 'onboarding_payment_return', methods: ['GET'])]
    public function paymentReturn(string $token, Request $request): RedirectResponse
    {
        if ($this->getSessionAccountId($request) <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        return $this->redirectToRoute('onboarding_status_page', [
            'token' => $token,
            'payment' => 'success',
        ]);
    }

    #[Route('/register/payment-cancel/{token}', name: 'onboarding_payment_cancel', methods: ['GET'])]
    public function paymentCancel(string $token, Request $request): RedirectResponse
    {
        if ($this->getSessionAccountId($request) <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $sessionToken = (string) $request->getSession()->get('onboarding_provision_token', '');
        if ($sessionToken === $token) {
            $request->getSession()->remove('onboarding_provision_token');
        }

        return $this->redirectToRoute('onboarding_account_dashboard', ['payment' => 'cancelled']);
    }

    private function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }
}
