<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Identity\ApiKeyAuthenticator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
final readonly class SessionCredentialRequestListener
{
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!in_array(
            $request->attributes->get('_route'),
            ['nubit_admin_auth_refresh', 'nubit_admin_auth_logout'],
            strict: true,
        )) {
            return;
        }

        // These endpoints validate the refresh credential, not the access JWT.
        // A fake/stale Bearer must not bypass the cookie CSRF listener either.
        $request->headers->remove(JWTAuthenticator::AUTH_HEADER);
        $request->headers->remove(ApiKeyAuthenticator::HEADER);

        // Explicit signed refresh credentials are not ambient authentication.
        // Logout still enforces CSRF whenever it clears browser cookies.
        if ($request->attributes->get('_route') === 'nubit_admin_auth_refresh') {
            $body = json_decode($request->getContent(), associative: true);
            if (is_array($body) && is_string($body['refreshToken'] ?? null) && $body['refreshToken'] !== '') {
                $request->cookies->remove(JWTAuthenticator::AUTH_COOKIE);
                $request->cookies->remove(JWTAuthenticator::REFRESH_COOKIE);
            }
        }
    }
}
