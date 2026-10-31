<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Nubit\AdminBundle\Auth\CookieFactory;
use Nubit\AdminBundle\Auth\MercureSubscriberTokenService;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mobile auth responses contain tokens in JSON, so the bundle's cookie-mode
 * decorator is not invoked. Keep the Mercure subscriber cookie available to
 * native clients without exposing its JWT in the response body.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -16)]
final readonly class MercureJsonResponseCookieListener
{
    public function __construct(
        private MercureSubscriberTokenService $mercureTokenService,
        private CookieFactory $cookieFactory,
        private TenantContext $tenantContext,
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            !$response instanceof JsonResponse
            || !in_array(
                $request->attributes->get('_route'),
                ['nubit_admin_auth_login', 'nubit_admin_auth_refresh'],
                true,
            )
        ) {
            return;
        }

        $data = json_decode((string) $response->getContent(), true);
        if (!is_array($data) || !is_string($data['token'] ?? null) || !is_int($data['expiresAt'] ?? null)) {
            return;
        }

        $tenantOrigin = null !== $this->tenantContext->getTenantDomain() ? $request->getSchemeAndHttpHost() : '';
        $audience = $request->getSchemeAndHttpHost() . '/.well-known/mercure';

        $response->headers->setCookie($this->cookieFactory->createSecureCookie(
            MercureCookieDecorator::MERCURE_COOKIE,
            $this->mercureTokenService->generateSubscriberToken($audience, [$tenantOrigin . '/api/*']),
            $data['expiresAt'],
            '/.well-known/mercure',
            null,
            Cookie::SAMESITE_LAX,
        ));
    }
}
