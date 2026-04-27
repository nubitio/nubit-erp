<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Context\TenantContext;
use App\Core\Security\Auth\ClientDetectorService;
use App\Core\Security\Auth\CookieFactoryService;
use App\Core\Security\Auth\JWTAuthenticator;
use App\Core\Security\Auth\MercureSubscriberTokenService;
use App\Core\Security\Auth\TokenGeneratorService;
use App\Organization\Entity\Branch;
use App\Entity\User;
use App\Organization\Repository\BranchRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthSelectBranchController extends AbstractController
{
    public function __construct(
        private readonly SecurityComponent $security,
        private readonly BranchRepository $branchRepository,
        private readonly TokenGeneratorService $tokenGenerator,
        private readonly ClientDetectorService $clientDetector,
        private readonly CookieFactoryService $cookieFactory,
        private readonly MercureSubscriberTokenService $mercureTokenService,
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Usuario no autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $branchId = $data['branchId'] ?? null;

        if (!is_string($branchId) || $branchId === '') {
            return new JsonResponse(['message' => 'Debe seleccionar una sucursal'], Response::HTTP_BAD_REQUEST);
        }

        $branch = $this->branchRepository->find($branchId);
        if (!$branch instanceof Branch) {
            return new JsonResponse(['message' => 'Sucursal no encontrada'], Response::HTTP_NOT_FOUND);
        }

        try {
            $tokenPair = $this->tokenGenerator->generateTokenPair($user, $branch);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate token pair for branch selection', [
                'user_id' => $user->getId(),
                'branch_id' => $branchId,
                'exception' => $e->getMessage(),
            ]);

            return new JsonResponse(['message' => 'Error al generar tokens'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
}
