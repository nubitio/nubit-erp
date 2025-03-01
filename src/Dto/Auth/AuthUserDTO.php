<?php

namespace App\Dto\Auth;

final readonly class AuthUserDTO
{
    private function __construct(
        public int $userId,
        public string $username,
        public ?int $roleId = null,
        public ?string $branchId = null,
    ) {
    }

    public static function createFromJWTPayload(array $jwtPayload): AuthUserDTO
    {
        return new self(
            userId: $jwtPayload['userId'],
            username: $jwtPayload['username'],
            roleId: $jwtPayload['role'],
            branchId: $jwtPayload['branchId'],
        );
    }
}
