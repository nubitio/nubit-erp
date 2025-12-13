<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use App\Entity\User;

final readonly class TokenPairDTO
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $accessTokenExpiresAt,
        public int $refreshTokenExpiresAt,
        public User $user,
        public ?string $branchId = null,
        public ?string $branchName = null,
    ) {
    }

    public function toArray(bool $includeTokens = true): array
    {
        $data = [
            'user' => [
                'username' => $this->user->getUsername(),
                'userId' => $this->user->getId(),
                'role' => $this->user->getRole()?->getId(),
                'roles' => $this->user->getRoles(),
                'branchId' => $this->branchId,
                'branchName' => $this->branchName,
            ],
        ];

        if ($includeTokens) {
            $data['token'] = $this->accessToken;
            $data['refreshToken'] = $this->refreshToken;
            $data['expiresAt'] = $this->accessTokenExpiresAt;
        }

        return $data;
    }
}
