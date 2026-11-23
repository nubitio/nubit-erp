<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Symfony\Component\HttpFoundation\Request;

/**
 * The `aud` claim of the Mercure subscriber token: the hub only accepts a token whose audience is its own
 * resource identifier.
 *
 * By default the hub derives that identifier from each request (scheme + host + `/.well-known/mercure`), and the
 * token is built from the request PHP sees. Behind a proxy that terminates TLS the two can disagree on the
 * scheme (PHP sees `https` through X-Forwarded-Proto, the hub sees the plain-HTTP hop, or the other way round):
 * the hub then answers 401 to every subscription and nothing arrives in real time.
 *
 * Setting `MERCURE_AUDIENCE` pins one canonical audience, and the hub is told the same value with its
 * `resource_identifier` directive (see docker-compose.prod.yml), so neither side depends on what it detects.
 */
final readonly class MercureAudience
{
    private const string HUB_PATH = '/.well-known/mercure';

    public function __construct(
        private ?string $pinned = null,
    ) {}

    public function forRequest(Request $request): string
    {
        $pinned = trim((string) $this->pinned);

        return $pinned !== '' ? $pinned : $request->getSchemeAndHttpHost() . self::HUB_PATH;
    }
}
