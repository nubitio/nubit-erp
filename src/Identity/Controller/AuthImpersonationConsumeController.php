<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Auth\UserProvider;
use Firebase\JWT\ExpiredException;
use Nubit\AdminBundle\Auth\CookieFactory;
use Nubit\AdminBundle\Auth\CsrfTokenPolicy;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Nubit\AdminBundle\Auth\TokenClaimsProviderInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Throwable;

/**
 * Public counterpart to ImpersonationApiController: the control-plane admin's
 * browser lands here (on the *target tenant's* domain) with the minted access
 * token in the URL fragment, and this exchanges it for the same httpOnly
 * AUTH_TOKEN cookie a normal login would set. No refresh token is issued here
 * either — the token this endpoint accepts already carries no refresh
 * counterpart, so the resulting session expires with the access token.
 */
final class AuthImpersonationConsumeController extends AbstractController
{
    public function __construct(
        private readonly JWTManagerInterface $jwtManager,
        private readonly UserProvider $userProvider,
        private readonly CookieFactory $cookieFactory,
        private readonly TokenClaimsProviderInterface $claimsProvider,
        private readonly ?TenantContext $tenantContext = null,
    ) {}

    #[Route('/api/auth/impersonate/consume', name: 'api_auth_impersonate_consume', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = trim((string) ($payload['token'] ?? ''));
        if ($token === '') {
            return $this->json(['error' => 'Token is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $claims = $this->jwtManager->decode($token);
        } catch (ExpiredException) {
            return $this->json(['error' => 'El enlace de acceso expiró'], Response::HTTP_UNAUTHORIZED);
        } catch (Throwable) {
            return $this->json(['error' => 'Token de acceso inválido'], Response::HTTP_UNAUTHORIZED);
        }

        // Only tokens minted by the impersonation flow carry this claim — a
        // regular access/refresh token must not be redeemable through here.
        if (!isset($claims['impersonatedBy'])) {
            return $this->json(['error' => 'Token de acceso inválido'], Response::HTTP_UNAUTHORIZED);
        }

        $tokenTenantName = $claims['tenantName'] ?? null;
        $currentTenantName = $this->tenantContext?->getTenantName();
        if (null !== $tokenTenantName && null !== $currentTenantName && $tokenTenantName !== $currentTenantName) {
            return $this->json(['error' => 'Token de acceso inválido'], Response::HTTP_UNAUTHORIZED);
        }

        $username = $claims['username'] ?? null;
        if (!is_string($username) || $username === '') {
            return $this->json(['error' => 'Token de acceso inválido'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($username);
        } catch (UserNotFoundException) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $expiresAt = (int) ($claims['exp'] ?? (time() + 60));

        $response = $this->json([
            'ok' => true,
            'user' => $this->claimsProvider->userData($user),
        ]);

        $response->headers->setCookie($this->cookieFactory->createSecureCookie(
            JWTAuthenticator::AUTH_COOKIE,
            $token,
            $expiresAt,
        ));
        // Branch selection and later writes use cookie authentication, just
        // like a normal login, and need a readable double-submit CSRF token.
        $response->headers->setCookie($this->cookieFactory->createCsrfCookie(
            CsrfTokenPolicy::COOKIE_NAME,
            CsrfTokenPolicy::generate(),
            $expiresAt,
        ));

        return $response;
    }
}
