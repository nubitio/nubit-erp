<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JWTManager
{
    private const string JWT_ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function encode(array $payload, ?int $expiresIn = null): string
    {
        if ($expiresIn !== null) {
            $payload['exp'] = time() + $expiresIn;
        }

        return JWT::encode($payload, $this->secret, self::JWT_ALGORITHM);
    }

    public function decode(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secret, self::JWT_ALGORITHM));
    }

    public function verify(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (\Exception) {
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
        } catch (\Exception) {
            return true;
        }
    }
}
