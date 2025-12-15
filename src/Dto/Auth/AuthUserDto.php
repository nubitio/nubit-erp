<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final readonly class AuthUserDto
{
    private function __construct(
        public int $userId,
        public string $username,
        public ?int $role = null,
        public ?string $roles = null,
    ) {
    }

    public static function createFromJWTPayload(array $jwtPayload): AuthUserDto
    {
        return new self(
            userId: $jwtPayload['userId'],
            username: $jwtPayload['username'],
            role: $jwtPayload['role'],
            roles: $jwtPayload['roles']
        );
    }
}
