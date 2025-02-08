<?php

namespace App\Auth\Api;

use App\Entity\User;

final readonly class AuthUserDTO
{
    private function __construct(
        public int $userId,
        public string $username,
        public ?int $roleId = null,
        public ?string $branchId = null,
    ) {
    }

    public static function createFromUserDTO(User $user): AuthUserDTO
    {
        return new self(
            userId: $user->getId(),
            username: $user->getUsername(),
            roleId: $user->getRole()?->getId(),
        );
    }

    public static function createFromJWTPayload(array $jwtPayload): AuthUserDTO
    {
        return new self(
            userId: $jwtPayload['id'],
            username: $jwtPayload['username'],
            roleId: $jwtPayload['role'],
            branchId: $jwtPayload['branchId'],
        );
    }
}
