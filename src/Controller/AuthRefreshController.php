<?php

namespace App\Controller;

use App\Core\Security\Auth\JWTAuthenticator;
use App\Core\Security\Auth\JWTManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthRefreshController extends AbstractController
{
    public function __construct(
        private readonly JWTManager $jwtManager,
        private readonly int $accessTokenTtl,
    ) {
    }

    #[Route('/api/v1/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $refreshToken = $request->cookies->get(JWTAuthenticator::REFRESH_COOKIE);
        if (!$refreshToken) {
            return new JsonResponse(['message' => 'No refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtManager->decode($refreshToken);
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if (($payload['type'] ?? null) !== 'refresh') {
            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $now = new \DateTimeImmutable();
        if (isset($payload['exp']) && $payload['exp'] < $now->getTimestamp()) {
            return new JsonResponse(['message' => 'Expired refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = $this->jwtManager->encode([
            ...$payload,
            'type' => 'access',
            'exp' => $now->getTimestamp() + $this->accessTokenTtl,
            'iat' => $now->getTimestamp(),
        ]);

        $response = new JsonResponse([
            'user' => [
                'username' => $payload['username'] ?? '',
                'userId' => $payload['userId'] ?? null,
                'role' => $payload['role'] ?? null,
                'roles' => $payload['roles'] ?? [],
                'branchId' => $payload['branchId'] ?? null,
            ],
        ]);

        $accessCookie = Cookie::create(
            JWTAuthenticator::AUTH_COOKIE,
            $accessToken,
            $now->getTimestamp() + $this->accessTokenTtl,
            '/',
            null,
            true
        );

        $response->headers->setCookie($accessCookie);

        return $response;
    }
}
