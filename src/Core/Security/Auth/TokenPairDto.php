<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use App\Dto\Auth\AuthResponseDto;
use App\Entity\User;

final readonly class TokenPairDto
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

    public function toAuthResponseDto(bool $includeTokens = true): AuthResponseDto
    {
        return AuthResponseDto::createFromTokenPair($this, $includeTokens);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeTokens = true): array
    {
        $data = [
            'user' => [
                'username' => $this->user->getUsername(),
                'userId' => $this->user->getId(),
                'role' => $this->user->getRole()?->getId(),
                'roles' => $this->user->getRoles(),
            ],
        ];

        if ($this->branchId !== null) {
            $data['branch'] = [
                'id' => $this->branchId,
                'name' => $this->branchName,
            ];
        }

        if ($includeTokens) {
            $data['token'] = $this->accessToken;
            $data['refreshToken'] = $this->refreshToken;
            $data['expiresAt'] = $this->accessTokenExpiresAt;
        }

        return $data;
    }
}
