<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Api;

use App\ControlPlane\Onboarding\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/account')]
final class AccountSessionApiController extends AbstractController
{
    use AccountSessionTrait;

    public function __construct(
        private readonly AccountService $accountService,
        private readonly RateLimiterFactoryInterface $onboardingRegistrationLimiter,
        private readonly RateLimiterFactoryInterface $onboardingVerificationLimiter,
    ) {}

    #[Route('/config', name: 'api_account_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        return $this->json([
            'domainSuffix' => $this->onboardingDomainSuffix(),
        ]);
    }

    #[Route('/session', name: 'api_account_session', methods: ['GET'])]
    public function session(Request $request): JsonResponse
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            $pendingEmail = (string) $request->getSession()->get('onboarding_pending_email', '');

            return $this->json([
                'authenticated' => false,
                'pendingEmail' => $pendingEmail !== '' ? $pendingEmail : null,
            ]);
        }

        $account = $this->accountService->findById($accountId);
        if ($account === null) {
            $this->clearAccountSession($request);

            return $this->json(['authenticated' => false, 'pendingEmail' => null]);
        }

        return $this->json([
            'authenticated' => true,
            'account' => [
                'id' => $account['id'],
                'email' => $account['email'],
            ],
        ]);
    }

    #[Route('/register', name: 'api_account_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        $limiter = $this->onboardingRegistrationLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(
                ['error' => 'Demasiados intentos de registro. Intenta mas tarde.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            $registered = $this->accountService->register($email, $password);
            $request->getSession()->set('onboarding_pending_email', $registered['email']);

            return $this->json(['ok' => true, 'email' => $registered['email']]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/verify', name: 'api_account_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $email = (string) ($payload['email'] ?? '');
        $code = (string) ($payload['code'] ?? '');

        $limiter = $this->onboardingVerificationLimiter->create('verify_' . mb_strtolower(trim($email)));
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(
                ['error' => 'Demasiados intentos de verificacion. Intenta mas tarde.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        try {
            $accountId = $this->accountService->verifyEmailCode($email, $code);
            $this->bindAccountSession($request, $accountId, $email);

            return $this->json(['ok' => true, 'accountId' => $accountId]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/login', name: 'api_account_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        try {
            $account = $this->accountService->authenticate($email, $password);
            if ($account === null) {
                return $this->json(['error' => 'Credenciales invalidas.'], Response::HTTP_UNAUTHORIZED);
            }

            $this->bindAccountSession($request, $account['id'], $account['email']);

            return $this->json(['ok' => true]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage(), 'requiresVerification' => true], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/logout', name: 'api_account_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->clearAccountSession($request);

        return $this->json(['ok' => true]);
    }

}