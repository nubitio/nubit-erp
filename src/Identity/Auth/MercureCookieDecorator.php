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
        $tenantDomain = $this->tenantContext->getTenantDomain() ?? '';
        $tenantOrigin = '' !== $tenantDomain ? 'https://' . $tenantDomain : '';

        $response->headers->setCookie($this->cookieFactory->createSecureCookie(
            self::MERCURE_COOKIE,
            $this->mercureTokenService->generateSubscriberToken([$tenantOrigin . '/api/*']),
            $tokenPair->accessTokenExpiresAt,
            '/.well-known/mercure',
            null,
            Cookie::SAMESITE_LAX,
        ));
    }
}
