<?php

declare(strict_types=1);

namespace App\Efact\Mercure;

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
 * By returning the tenant's own domain, the SSE connection is same-origin:
 *   - Cookie is sent automatically ✓
 *   - No CORS required ✓
 *   - Caddy already reverse-proxies /.well-known/mercure* → mercure:80 ✓
 */
final readonly class TenantAwareMercureHub implements HubInterface
{
    /** Path proxied by Caddy to the internal Mercure container. */
    private const string HUB_PATH = '/.well-known/mercure';

    public function __construct(
        private HubInterface $inner,
        private RequestStack $requestStack,
    ) {
    }

    public function getPublicUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request !== null) {
            return $request->getSchemeAndHttpHost() . self::HUB_PATH;
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
