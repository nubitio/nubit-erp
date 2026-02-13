<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use App\Onboarding\Service\AccountService;
use App\Onboarding\Service\OnboardingService;
use App\Onboarding\Service\SubdomainValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TenantWizardController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly SubdomainValidator $subdomainValidator,
        private readonly OnboardingService $onboardingService,
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
    public function provision(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('onboarding_wizard', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_wizard');
        }

        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->redirectToRoute('onboarding_account_login');
        }

        $tenantName = trim((string) $request->request->get('tenant_name', ''));
        $subdomain = trim((string) $request->request->get('subdomain', ''));
        $plan = trim((string) $request->request->get('plan', 'free'));
        $adminPassword = (string) $request->request->get('admin_password', '');

        if ($subdomain === '' && $tenantName !== '') {
            $subdomain = $this->subdomainValidator->slugifyTenantName($tenantName);
        }

        try {
            $result = $this->onboardingService->startProvisioning(
                $accountId,
                $tenantName,
                $subdomain,
                $plan,
                $adminPassword,
            );

            return $this->redirectToRoute('onboarding_status_page', ['token' => $result['token']]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_wizard', [
                'tenant_name' => $tenantName,
                'subdomain' => $subdomain,
            ]);
        }
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
            'error' => $status['error_message'],
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
}
