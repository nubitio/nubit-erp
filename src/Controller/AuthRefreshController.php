<?php

namespace App\Controller;

use App\Core\Security\Auth\JWTAuthenticator;
use App\Core\Security\Auth\JWTManager;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthRefreshController extends AbstractController
{
    public function __construct(
        private readonly JWTManager $jwtManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
    ) {
    }

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

        $storedToken = $this->refreshTokenRepository->findActiveByHash(hash('sha256', $refreshToken));
        if (null === $storedToken) {
            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if ($storedToken->getJti() !== ($payload['jti'] ?? null)) {
            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if ($storedToken->getExpiresAt() < $now) {
            return new JsonResponse(['message' => 'Expired refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($payload['userId'] ?? 0);
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $storedToken->revoke($now);

        $accessToken = $this->jwtManager->encode([
            ...$payload,
            'type' => 'access',
            'exp' => $now->getTimestamp() + $this->accessTokenTtl,
            'iat' => $now->getTimestamp(),
        ]);

        $refreshTokenJti = bin2hex(random_bytes(16));
        $newRefreshToken = $this->jwtManager->encode([
            ...$payload,
            'type' => 'refresh',
            'exp' => $now->getTimestamp() + $this->refreshTokenTtl,
            'jti' => $refreshTokenJti,
            'iat' => $now->getTimestamp(),
        ]);

        $newRefreshTokenEntity = (new RefreshToken())
            ->setJti($refreshTokenJti)
            ->setTokenHash(hash('sha256', $newRefreshToken))
            ->setUser($user)
            ->setExpiresAt((new \DateTimeImmutable())->setTimestamp($now->getTimestamp() + $this->refreshTokenTtl));

        $this->entityManager->persist($newRefreshTokenEntity);
        $this->entityManager->flush();

        $response = new JsonResponse([
            'user' => [
                'username' => $payload['username'] ?? '',
                'userId' => $payload['userId'] ?? null,
                'role' => $payload['role'] ?? null,
                'roles' => $payload['roles'] ?? [],
                'branchId' => $payload['branchId'] ?? null,
                'branchName' => $payload['branchName'] ?? null,
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
        $refreshCookie = Cookie::create(
            JWTAuthenticator::REFRESH_COOKIE,
            $newRefreshToken,
            $newRefreshTokenEntity->getExpiresAt()->getTimestamp(),
            '/',
            null,
            true
        );

        $response->headers->setCookie($accessCookie);
        $response->headers->setCookie($refreshCookie);

        return $response;
    }
}
