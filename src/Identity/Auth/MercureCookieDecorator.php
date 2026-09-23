<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Nubit\AdminBundle\Auth\CookieFactory;
use Nubit\AdminBundle\Auth\LoginResponseDecoratorInterface;
use Nubit\AdminBundle\Auth\MercureSubscriberTokenService;
use Nubit\AdminBundle\Auth\TokenPair;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Attaches the Mercure subscriber JWT cookie to every cookie-mode auth
 * response (login, refresh, select-branch), scoped to the hub path.
 */
final readonly class MercureCookieDecorator implements LoginResponseDecoratorInterface
{
    public const string MERCURE_COOKIE = 'mercureAuthorization';

    public function __construct(
        private MercureSubscriberTokenService $mercureTokenService,
        private CookieFactory $cookieFactory,
        private TenantContext $tenantContext,
    ) {}

    public function decorate(JsonResponse $response, UserInterface $user, TokenPair $tokenPair, Request $request): void
    {
        // Must match the origin the browser will actually call for Mercure
        // subscriptions (scheme + host + port), not just the tenant's bare
        // domain — a mismatched port or scheme makes the hub reject every
        // subscription with a silent 401.
        $tenantDomain = $this->tenantContext->getTenantDomain() ?? '';
        $tenantOrigin = '' !== $tenantDomain ? $request->getSchemeAndHttpHost() : '';
        // The token's `aud` must equal the exact URL the browser uses to
        // reach the hub — the hub derives its resource identifier from the
        // request, so this varies per tenant subdomain.
        $audience = $request->getSchemeAndHttpHost() . '/.well-known/mercure';

        $response->headers->setCookie($this->cookieFactory->createSecureCookie(
            self::MERCURE_COOKIE,
            $this->mercureTokenService->generateSubscriberToken($audience, [$tenantOrigin . '/api/*']),
            $tokenPair->accessTokenExpiresAt,
            '/.well-known/mercure',
            null,
            Cookie::SAMESITE_LAX,
        ));
    }
}
