<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Firebase\JWT\JWT;

/**
 * Generates Mercure subscriber JWTs signed with MERCURE_JWT_SECRET.
 *
 * The subscriber JWT is issued as a cookie (mercureAuthorization) so the browser
 * can authenticate SSE connections to the Mercure hub without exposing the token
 * in JavaScript. The token grants access to all topics scoped to the tenant's origin.
 *
 * @see https://mercure.rocks/docs/hub/subscriptions#authorization
 */
final readonly class MercureSubscriberTokenService
{
    private const string JWT_ALGORITHM = 'HS256';

    public function __construct(
        private string $mercureJwtSecret,
        private int $accessTokenTtl,
    ) {
    }

    /**
     * Generate a subscriber JWT allowing the user to subscribe to any topic
     * under their tenant origin (e.g. https://tenant.efact.app/api/*).
     *
     * Uses a URI Template wildcard so one token covers all resource types.
     */
    public function generateSubscriberToken(string $tenantOrigin): string
    {
        $now = time();

        return JWT::encode(
            [
                'iat'     => $now,
                'exp'     => $now + $this->accessTokenTtl,
                'mercure' => [
                    'subscribe' => [
                        $tenantOrigin . '/api/*',
                    ],
                ],
            ],
            $this->mercureJwtSecret,
            self::JWT_ALGORITHM
        );
    }
}
