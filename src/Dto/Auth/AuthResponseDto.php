<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Core\Security\Auth\TokenPairDto;

class AuthResponseDto
{
    public function __construct(
        public AuthUserDto $user,
        public ?AuthBranchDto $branch,
        public string $token,
        public string $refreshToken,
        public int $expiresAt,
    ) {
    }

    public static function createFromTokenPair(TokenPairDto $param): AuthResponseDto
    {
        return new self(
            user: AuthUserDto::createFromJWTPayload([
                'userId' => $param->user->getId(),
                'username' => $param->user->getUsername(),
                'role' => $param->user->getRole()?->getId(),
                'roles' => $param->user->getRoles(),
            ]),
            branch: $param->branchId !== null ? new AuthBranchDTO(
                id: $param->branchId,
                name: $param->branchName,
            ) : null,
            token: $param->accessToken,
            refreshToken: $param->refreshToken,
            expiresAt: $param->accessTokenExpiresAt,
        );
    }
}
