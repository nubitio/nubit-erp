<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Core\Security\Auth\ClientDetectorService;
use App\Core\Security\Auth\CookieFactoryService;
use App\Core\Security\Auth\JWTAuthenticator;
use App\Core\Security\Auth\JWTManager;
use App\Core\Security\Auth\MercureSubscriberTokenService;
use App\Core\Security\Auth\TokenGeneratorService;
use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
use App\Identity\Repository\RefreshTokenRepository;
use App\Core\Context\TenantContext;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthRefreshController extends AbstractController
{
    public function __construct(
        private readonly JWTManager $jwtManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenGeneratorService $tokenGenerator,
        private readonly ClientDetectorService $clientDetector,
        private readonly CookieFactoryService $cookieFactory,
        private readonly MercureSubscriberTokenService $mercureTokenService,
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $logger,
        private readonly BranchRepository $branchRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = json_decode($request->getContent(), true);
        $refreshToken = $body['refreshToken'] ?? $request->cookies->get(JWTAuthenticator::REFRESH_COOKIE);

        if (!$refreshToken) {
            $this->logger->warning('Refresh token missing in request', [
                'ip' => $request->getClientIp() ?? 'unknown',
            ]);

            return new JsonResponse(['message' => 'No refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtManager->decode($refreshToken);
        } catch (Throwable $e) {
            $this->logger->warning('Invalid refresh token', [
                'exception' => $e->getMessage(),
                'ip' => $request->getClientIp() ?? 'unknown',
            ]);

            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $validationError = $this->validateRefreshToken($payload);
        if ($validationError) {
            return $validationError;
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($payload['userId'] ?? 0);
        if (!$user instanceof User) {
            $this->logger->error('User not found for refresh token', [
                'user_id' => $payload['userId'] ?? null,
            ]);

            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $storedToken = $this->refreshTokenRepository->findActiveByHash(hash('sha256', $refreshToken));
        if (!$storedToken instanceof RefreshToken) {
            $this->logger->warning('Refresh token not found in database', [
                'user_id' => $user->getId(),
            ]);

            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $storedToken->revoke(new DateTimeImmutable());

        $branch = null;
        if (!empty($payload['branchId'])) {
            $branch = $this->branchRepository->find($payload['branchId']);
            if ($branch === null) {
                return new JsonResponse(['message' => 'Invalid branch'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $tokenPair = $this->tokenGenerator->generateTokenPair($user, $branch);

            $this->logger->info('Token refreshed successfully', [
                'user_id' => $user->getId(),
                'old_jti' => $storedToken->getJti(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to generate new token pair during refresh', [
                'user_id' => $user->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['message' => 'Error generating tokens'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $isMobileClient = $this->clientDetector->isMobileClient($request);
        $responseData = $tokenPair->toArray($isMobileClient);
        $response = new JsonResponse($responseData);

        if (!$isMobileClient) {
            $response->headers->setCookie(
                $this->cookieFactory->createSecureCookie(
                    JWTAuthenticator::AUTH_COOKIE,
                    $tokenPair->accessToken,
                    $tokenPair->accessTokenExpiresAt
                )
            );
            $response->headers->setCookie(
                $this->cookieFactory->createSecureCookie(
                    JWTAuthenticator::REFRESH_COOKIE,
                    $tokenPair->refreshToken,
                    $tokenPair->refreshTokenExpiresAt
                )
            );

            $tenantDomain = $this->tenantContext->getTenantDomain() ?? '';
            $tenantOrigin = $tenantDomain !== '' ? 'https://' . $tenantDomain : '';
            $response->headers->setCookie(
                $this->cookieFactory->createSecureCookie(
                    JWTAuthenticator::MERCURE_COOKIE,
                    $this->mercureTokenService->generateSubscriberToken($tenantOrigin),
                    $tokenPair->accessTokenExpiresAt,
                    '/.well-known/mercure',
                    null,
                    Cookie::SAMESITE_LAX
                )
            );
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateRefreshToken(array $payload): ?JsonResponse
    {
        if (($payload['type'] ?? null) !== 'refresh') {
            $this->logger->warning('Token is not a refresh token', [
                'type' => $payload['type'] ?? 'null',
            ]);

            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $now = new DateTimeImmutable();
        if (isset($payload['exp']) && $payload['exp'] < $now->getTimestamp()) {
            $this->logger->info('Refresh token expired', [
                'exp' => date('Y-m-d H:i:s', $payload['exp']),
            ]);

            return new JsonResponse(['message' => 'Expired refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if (!isset($payload['jti'])) {
            $this->logger->error('Refresh token missing JTI claim');

            return new JsonResponse(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }
}
