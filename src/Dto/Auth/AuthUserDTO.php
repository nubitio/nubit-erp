<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final readonly class AuthUserDTO
{
    private function __construct(
        public int $userId,
        public string $username,
        public ?int $roleId = null,
    ) {
    }

    public static function createFromJWTPayload(array $jwtPayload): AuthUserDTO
    {
        return new self(
            userId: $jwtPayload['userId'],
            username: $jwtPayload['username'],
            roleId: $jwtPayload['role'],
        );
    }
}
