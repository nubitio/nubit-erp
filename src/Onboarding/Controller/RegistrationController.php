<?php

declare(strict_types=1);

namespace App\Onboarding\Controller;

use App\Onboarding\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RateLimiterFactoryInterface $onboardingRegistrationLimiter,
        private readonly RateLimiterFactoryInterface $onboardingVerificationLimiter,
    ) {
    }

    #[Route('/register', name: 'onboarding_register', methods: ['GET'])]
    public function registerForm(Request $request): Response
    {
        if ($this->getSessionAccountId($request) > 0) {
            return $this->redirectToRoute('onboarding_wizard');
        }

        return $this->render('onboarding/register.html.twig', [
            'email' => (string) $request->query->get('email', ''),
        ]);
    }

    #[Route('/register', name: 'onboarding_register_submit', methods: ['POST'])]
    public function registerSubmit(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('onboarding_register', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_register');
        }

        $limiter = $this->onboardingRegistrationLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Demasiados intentos de registro. Intenta mas tarde.');

            return $this->redirectToRoute('onboarding_register');
        }

        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        try {
            $registered = $this->accountService->register($email, $password);
            $session = $request->getSession();
            $session->set('onboarding_pending_email', $registered['email']);

            $this->addFlash('success', 'Te enviamos un codigo de 6 digitos por email.');

            return $this->redirectToRoute('onboarding_verify', ['email' => $registered['email']]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_register', ['email' => $email]);
        }
    }

    #[Route('/register/verify', name: 'onboarding_verify', methods: ['GET'])]
    public function verifyForm(Request $request): Response
    {
        if ($this->getSessionAccountId($request) > 0) {
            return $this->redirectToRoute('onboarding_wizard');
        }

        $sessionEmail = (string) $request->getSession()->get('onboarding_pending_email', '');
        $email = (string) ($request->query->get('email', $sessionEmail));

        return $this->render('onboarding/verify.html.twig', [
            'email' => $email,
        ]);
    }

    #[Route('/register/verify', name: 'onboarding_verify_submit', methods: ['POST'])]
    public function verifySubmit(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('onboarding_verify', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguridad invalido.');

            return $this->redirectToRoute('onboarding_verify');
        }

        $email = (string) $request->request->get('email', '');

        $limiter = $this->onboardingVerificationLimiter->create('verify_' . mb_strtolower(trim($email)));
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Demasiados intentos de verificacion. Intenta mas tarde.');

            return $this->redirectToRoute('onboarding_verify', ['email' => $email]);
        }

        $code = (string) $request->request->get('code', '');

        try {
            $accountId = $this->accountService->verifyEmailCode($email, $code);
            $session = $request->getSession();
            $session->set('onboarding_account_id', $accountId);
            $session->set('onboarding_account_email', mb_strtolower(trim($email)));
            $session->remove('onboarding_pending_email');

            $this->addFlash('success', 'Email verificado. Ya puedes crear tu tenant.');

            return $this->redirectToRoute('onboarding_wizard');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('onboarding_verify', ['email' => $email]);
        }
    }

    private function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }
}
