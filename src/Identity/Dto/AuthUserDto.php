<?php

declare(strict_types=1);

namespace App\Identity\Dto;

final readonly class AuthUserDto
{
    public function __construct(
        public int $userId,
        public string $username,
        public ?int $role = null,
        /** @var array<string>|null */
        public ?array $roles = null,
    ) {}

    /**
     * @param array<string, mixed> $jwtPayload
     */
    public static function createFromJWTPayload(array $jwtPayload): AuthUserDto
    {
        return new self(
            userId: $jwtPayload['userId'],
            username: $jwtPayload['username'],
            role: $jwtPayload['role'],
            roles: $jwtPayload['roles'],
        );
    }
}
