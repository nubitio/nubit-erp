<?php

declare(strict_types=1);

namespace App\Identity\Dto;

class AuthResponseDto
{
    public function __construct(
        public AuthUserDto $user,
        public ?AuthBranchDto $branch = null,
        public ?string $token = null,
        public ?string $refreshToken = null,
        public ?int $expiresAt = null,
    ) {}
}
