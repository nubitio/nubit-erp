<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use App\Onboarding\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    #[Route('/account/login', name: 'onboarding_account_login', methods: ['GET'])]
    public function loginForm(Request $request): Response
    {
        if ($this->getSessionAccountId($request) > 0) {
            return $this->redirectToRoute('onboarding_account_dashboard');
        }

        return $this->render('onboarding/account/login.html.twig', [
            'email' => (string) $request->query->get('email', ''),
        ]);
    }

    #[Route('/account/login', name: 'onboarding_account_login_submit', methods: ['POST'])]
    public function loginSubmit(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('onboarding_account_login', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_account_login');
        }

        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        try {
            $account = $this->accountService->authenticate($email, $password);
            if ($account === null) {
                $this->addFlash('error', 'Credenciales invalidas.');

                return $this->redirectToRoute('onboarding_account_login', ['email' => $email]);
            }

            $session = $request->getSession();
            $session->set('onboarding_account_id', $account['id']);
            $session->set('onboarding_account_email', $account['email']);

            return $this->redirectToRoute('onboarding_account_dashboard');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_verify', ['email' => $email]);
        }
    }

    #[Route('/account/dashboard', name: 'onboarding_account_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
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

        return $this->render('onboarding/account/dashboard.html.twig', [
            'account' => $account,
            'tenants' => $this->accountService->listAccountTenants($accountId),
            'provisions' => $this->normalizeProvisions($this->accountService->listAccountProvisions($accountId)),
        ]);
    }

    #[Route('/account/dashboard/provisions', name: 'onboarding_account_dashboard_provisions', methods: ['GET'])]
    public function provisions(Request $request): JsonResponse
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $provisions = $this->normalizeProvisions($this->accountService->listAccountProvisions($accountId));

        return $this->json([
            'items' => $provisions,
        ]);
    }

    #[Route(
        '/account/dashboard/provisions/{provisionId}/dismiss',
        name: 'onboarding_account_dashboard_provision_dismiss',
        methods: ['POST']
    )]
    public function dismissProvision(Request $request, int $provisionId): JsonResponse
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('onboarding_dismiss_provision', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $dismissed = $this->accountService->dismissFailedProvision($accountId, $provisionId);
        if (!$dismissed) {
            return $this->json(['error' => 'Provision not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/account/logout', name: 'onboarding_account_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        if ($this->isCsrfTokenValid('onboarding_account_logout', (string) $request->request->get('_token'))) {
            $request->getSession()->remove('onboarding_account_id');
            $request->getSession()->remove('onboarding_account_email');
            $request->getSession()->remove('onboarding_pending_email');
        }

        return $this->redirectToRoute('onboarding_account_login');
    }

    private function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProvisions(array $rows): array
    {
        return array_map(function (array $row): array {
            $domain = (string) ($row['domain'] ?? '');
            $row['tenant_url'] = $domain !== '' ? 'https://' . $domain : '';

            return $row;
        }, $rows);
    }
}
