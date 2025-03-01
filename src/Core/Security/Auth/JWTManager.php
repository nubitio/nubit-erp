<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTManager
{
    private const string JWT_ALGORITHM = 'HS256';

    public function __construct(private readonly string $secret)
    {
    }

    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->secret, self::JWT_ALGORITHM);
    }

    public function decode(string $token): array
    {
        return (array)JWT::decode($token, new Key($this->secret, self::JWT_ALGORITHM));
    }
}
