<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Security\Auth\ClientDetectorService;
use App\Core\Security\Auth\CookieFactoryService;
use App\Core\Security\Auth\JWTAuthenticator;
use App\Core\Security\Auth\RefreshTokenService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthLogoutController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly ClientDetectorService $clientDetector,
        private readonly CookieFactoryService $cookieFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);
        $refreshToken = $body['refreshToken'] ?? $request->cookies->get(JWTAuthenticator::REFRESH_COOKIE);

        if ($refreshToken) {
            try {
                $this->refreshTokenService->revokeByToken($refreshToken);

                $this->logger->info('User logged out successfully', [
                    'ip' => $request->getClientIp() ?? 'unknown',
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Error revoking refresh token on logout', [
                    'exception' => $e->getMessage(),
                    'ip' => $request->getClientIp() ?? 'unknown',
                ]);
            }
        }

        $response = new JsonResponse(['message' => 'ok']);

        if (!$this->clientDetector->isMobileClient($request)) {
            $response->headers->setCookie(
                $this->cookieFactory->createExpiredCookie(JWTAuthenticator::AUTH_COOKIE)
            );
            $response->headers->setCookie(
                $this->cookieFactory->createExpiredCookie(JWTAuthenticator::REFRESH_COOKIE)
            );
        }

        return $response;
    }
}
