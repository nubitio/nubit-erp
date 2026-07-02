<?php

declare(strict_types=1);

namespace App\Efact\Mercure;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

/**
 * Decorates the Mercure hub so that getPublicUrl() returns the current
 * request's scheme+host + "/.well-known/mercure" instead of the static
 * MERCURE_PUBLIC_URL env var.
 *
 * ## Why this matters in a multi-tenant SaaS
 *
 * Each tenant lives on its own subdomain (e.g. jawka.efact.app).  If the
 * static MERCURE_PUBLIC_URL points to a central hub domain (app.efact.app),
 * the browser's EventSource would be cross-origin:
 *   - The subscriber JWT cookie is set for jawka.efact.app → not sent to app.efact.app
 *   - CORS requires echoing the exact origin with credentials mode "include"
 *
 * By returning the tenant's own domain the SSE connection is same-origin:
 *   - Cookie is sent automatically ✓
 *   - No CORS required ✓
 *   - Caddy already reverse-proxies /.well-known/mercure* → mercure:80 ✓
 *
 * ## Scheme resolution
 *
 * In deployments where PHP runs behind an SSL-terminating proxy that does NOT
 * forward X-Forwarded-Proto, Symfony's $request->isSecure() returns false even
 * though the browser is on HTTPS.  To handle this we fall back to HTTPS in any
 * non-dev environment so the URL the browser receives always matches the page
 * origin (HTTPS in production).
 *
 * If a development environment intentionally runs on HTTP, set APP_ENV=dev and
 * the scheme will be taken from the request as-is.
 */
final readonly class TenantAwareMercureHub implements HubInterface
{
    /** Path proxied by Caddy to the internal Mercure container. */
    private const string HUB_PATH = '/.well-known/mercure';

    public function __construct(
        private HubInterface $inner,
        private RequestStack $requestStack,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {}

    public function getPublicUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            // Use the scheme Symfony detected (works when X-Forwarded-Proto is trusted).
            // In non-dev environments fall back to https: the browser is always on HTTPS
            // in production even when the internal proxy connection uses plain HTTP.
            $isProd = $this->environment !== 'dev' && $this->environment !== 'test';
            $scheme = $request->isSecure() || $isProd ? 'https' : 'http';

            return $scheme . '://' . $request->getHttpHost() . self::HUB_PATH;
        }

        // Fallback to the configured static URL (e.g. when called from a CLI command).
        return $this->inner->getPublicUrl();
    }

    public function publish(Update $update): string
    {
        return $this->inner->publish($update);
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->inner->getFactory();
    }
}
