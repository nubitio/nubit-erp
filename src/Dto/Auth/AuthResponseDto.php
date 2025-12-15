<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Core\Security\Auth\TokenPairDto;

class AuthResponseDto
{
    public function __construct(
        public AuthUserDto $user,
        public ?AuthBranchDto $branch = null,
        public ?string $token = null,
        public ?string $refreshToken = null,
        public ?int $expiresAt = null,
    ) {
    }

    public static function createFromTokenPair(TokenPairDto $param, bool $includeTokens = true): self
    {
        return new self(
            user: new AuthUserDto(
                userId: $param->user->getId(),
                username: $param->user->getUsername(),
                role: $param->user->getRole()?->getId(),
                roles: $param->user->getRoles(),
            ),
            branch: $param->branchId !== null ? new AuthBranchDto(
                id: $param->branchId,
                name: $param->branchName,
            ) : null,
            token: $includeTokens ? $param->accessToken : null,
            refreshToken: $includeTokens ? $param->refreshToken : null,
            expiresAt: $includeTokens ? $param->accessTokenExpiresAt : null,
        );
    }
}
