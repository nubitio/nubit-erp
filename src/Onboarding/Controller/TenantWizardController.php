<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use App\Onboarding\Payment\BillingCycle;
use App\Onboarding\Service\AccountService;
use App\Onboarding\Service\OnboardingService;
use App\Onboarding\Service\SubdomainValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TenantWizardController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly SubdomainValidator $subdomainValidator,
        private readonly OnboardingService $onboardingService,
        private readonly RateLimiterFactory $onboardingTenantCreationLimiter,
    ) {
    }

    #[Route('/register/new-tenant', name: 'onboarding_wizard', methods: ['GET'])]
    public function wizard(Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $account = $this->accountService->findById($accountId);
        if ($account === null) {
            $request->getSession()->remove('onboarding_account_id');

            return $this->redirectToRoute('onboarding_account_login');
        }

        return $this->render('onboarding/wizard.html.twig', [
            'account' => $account,
            'tenant_name' => (string) $request->query->get('tenant_name', ''),
            'subdomain' => (string) $request->query->get('subdomain', ''),
        ]);
    }

    #[Route('/register/check-subdomain', name: 'onboarding_check_subdomain', methods: ['GET'])]
    public function checkSubdomain(Request $request): JsonResponse
    {
        $subdomain = (string) $request->query->get('subdomain', '');
        $result = $this->subdomainValidator->check($subdomain);

        return $this->json($result);
    }

    #[Route('/register/provision', name: 'onboarding_provision', methods: ['POST'])]
    public function provision(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('onboarding_wizard', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_wizard');
        }

        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $limiter = $this->onboardingTenantCreationLimiter->create('account_' . $accountId);
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Has alcanzado el limite de creacion de tenants por hoy. Intenta manana.');

            return $this->redirectToRoute('onboarding_account_dashboard');
        }

        $tenantName = trim((string) $request->request->get('tenant_name', ''));
        $subdomain = trim((string) $request->request->get('subdomain', ''));
        $plan = mb_strtolower(trim((string) $request->request->get('plan', 'free')));
        $gateway = mb_strtolower(trim((string) $request->request->get('gateway', 'paypal')));
        $billingCycleRaw = mb_strtolower(trim((string) $request->request->get('billing_cycle', 'monthly')));
        $billingCycle = BillingCycle::tryFrom($billingCycleRaw) ?? BillingCycle::Monthly;
        $adminPassword = (string) $request->request->get('admin_password', '');

        if ($subdomain === '' && $tenantName !== '') {
            $subdomain = $this->subdomainValidator->slugifyTenantName($tenantName);
        }

        try {
            if (!OnboardingService::isPaidPlan($plan)) {
                $result = $this->onboardingService->startFreeProvisioning(
                    $accountId,
                    $tenantName,
                    $subdomain,
                    $adminPassword,
                );

                return $this->redirectToRoute('onboarding_status_page', ['token' => $result['token']]);
            }

            // Paid plan: store admin password in session temporarily for post-payment provisioning
            $request->getSession()->set('onboarding_admin_password', $adminPassword);

            $result = $this->onboardingService->startPaidProvisioning(
                accountId: $accountId,
                tenantName: $tenantName,
                subdomain: $subdomain,
                plan: $plan,
                gateway: $gateway,
                billingCycle: $billingCycle,
                adminPassword: $adminPassword,
                returnUrl: $this->generateUrl('onboarding_payment_return', [
                    'token' => 'PLACEHOLDER',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                cancelUrl: $this->generateUrl('onboarding_payment_cancel', [
                    'token' => 'PLACEHOLDER',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            );

            // Store token for return
            $request->getSession()->set('onboarding_provision_token', $result['token']);

            return $this->redirect($result['approval_url']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_wizard', [
                'tenant_name' => $tenantName,
                'subdomain' => $subdomain,
            ]);
        }
    }

    #[Route('/register/payment-return/{token}', name: 'onboarding_payment_return', methods: ['GET'])]
    public function paymentReturn(string $token, Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $this->addFlash('success', 'Pago procesado. Tu empresa se esta creando.');

        return $this->redirectToRoute('onboarding_status_page', ['token' => $token]);
    }

    #[Route('/register/payment-cancel/{token}', name: 'onboarding_payment_cancel', methods: ['GET'])]
    public function paymentCancel(string $token, Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $sessionToken = (string) $request->getSession()->get('onboarding_provision_token', '');
        if ($sessionToken === $token) {
            $request->getSession()->remove('onboarding_provision_token');
        }

        $this->addFlash('warning', 'El pago fue cancelado. Puedes intentar nuevamente.');

        return $this->redirectToRoute('onboarding_account_dashboard');
    }

    #[Route('/register/status/{token}', name: 'onboarding_status_page', methods: ['GET'])]
    public function statusPage(string $token, Request $request): Response
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $status = $this->onboardingService->getProvisionStatus($token, $accountId);
        if ($status === null) {
            throw $this->createNotFoundException('Provision no encontrada.');
        }
        $status['error_message'] = $this->toPublicProvisionError((string) ($status['error_message'] ?? ''));

        return $this->render('onboarding/status.html.twig', [
            'token' => $token,
            'status' => $status,
            'tenant_url' => $this->resolveTenantUrl($status),
        ]);
    }

    #[Route('/register/status/{token}/poll', name: 'onboarding_status_poll', methods: ['GET'])]
    public function statusPoll(string $token, Request $request): JsonResponse
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $status = $this->onboardingService->getProvisionStatus($token, $accountId);
        if ($status === null) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'status' => $status['status'],
            'error' => $this->toPublicProvisionError((string) ($status['error_message'] ?? '')),
            'tenant_url' => $this->resolveTenantUrl($status),
        ]);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function resolveTenantUrl(array $status): string
    {
        $domain = (string) ($status['primary_domain'] ?? $status['domain'] ?? '');
        if ($domain === '') {
            return '';
        }

        return 'https://' . $domain;
    }

    private function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }

    private function toPublicProvisionError(string $errorMessage): string
    {
        $errorMessage = trim($errorMessage);
        if ($errorMessage === '') {
            return '';
        }

        $technicalMarkers = [
            'Provisioning command failed',
            'DB user password is required',
            'TENANT_DB_PASS',
            'ONBOARDING_TENANT_DB_PASS',
            'DATABASE_URL',
            '[ERROR]',
        ];

        foreach ($technicalMarkers as $marker) {
            if (str_contains($errorMessage, $marker)) {
                return 'No pudimos completar la creacion de tu empresa. Intenta nuevamente en unos minutos.';
            }
        }

        return $errorMessage;
    }
}
