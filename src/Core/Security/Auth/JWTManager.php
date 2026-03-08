<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicException;
use Psr\Log\LoggerInterface;

final readonly class JWTManager
{
    private const string JWT_ALGORITHM = 'HS256';

    public function __construct(
        private string $secret,
        private LoggerInterface $logger,
    ) {
        if (trim($this->secret) === '') {
            throw new LogicException('JWT secret is empty. Configure APP_SECRET with a non-empty value.');
        }
    }

    /**
     * No-op warm-up call used by AppSecretBootListener to force eager instantiation
     * when JWTManager is behind a Symfony lazy-service proxy.
     * The real side-effect is the LogicException thrown in __construct() when
     * APP_SECRET is empty — this method itself does nothing.
     */
    public function ping(): void
    {
        // Intentional no-op.
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload, ?int $expiresIn = null): string
    {
        if ($expiresIn !== null) {
            $payload['exp'] = time() + $expiresIn;
        }

        return JWT::encode($payload, $this->secret, self::JWT_ALGORITHM);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secret, self::JWT_ALGORITHM));
    }

    public function verify(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Invalid JWT token', ['exception' => $e]);
            return false;
        }
    }

    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->decode($token);

            if (!isset($payload['exp'])) {
                return false;
            }

            return time() > $payload['exp'];
        } catch (Exception $e) {
            $this->logger->error('Invalid JWT token', ['exception' => $e]);
            return true;
        }
    }
}
