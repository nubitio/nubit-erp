<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use App\Entity\Branch;
use App\Entity\RefreshToken;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TokenGeneratorService
{
    public function __construct(
        private JWTManager $jwtManager,
        private EntityManagerInterface $entityManager,
        private int $accessTokenTtl,
        private int $refreshTokenTtl,
    ) {
    }

    public function generateTokenPair(User $user): TokenPairDTO
    {
        $now = new DateTimeImmutable();
        $nowTimestamp = $now->getTimestamp();
        $accessTokenExpiresAt = $nowTimestamp + $this->accessTokenTtl;
        $refreshTokenExpiresAt = $nowTimestamp + $this->refreshTokenTtl;

        $payload = $this->buildPayload($user, $nowTimestamp);

        $accessToken = $this->jwtManager->encode([
            ...$payload,
            'exp' => $accessTokenExpiresAt,
        ]);

        $refreshTokenJti = bin2hex(random_bytes(16));
        $refreshToken = $this->jwtManager->encode([
            ...$payload,
            'exp' => $refreshTokenExpiresAt,
            'type' => 'refresh',
            'jti' => $refreshTokenJti,
        ]);

        $refreshTokenEntity = new RefreshToken()
            ->setJti($refreshTokenJti)
            ->setTokenHash(hash('sha256', $refreshToken))
            ->setUser($user)
            ->setExpiresAt(new DateTimeImmutable()->setTimestamp($refreshTokenExpiresAt));

        $this->entityManager->persist($refreshTokenEntity);
        $this->entityManager->flush();

        return new TokenPairDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            accessTokenExpiresAt: $accessTokenExpiresAt,
            refreshTokenExpiresAt: $refreshTokenExpiresAt,
            user: $user,
        );
    }

    private function buildPayload(User $user, int $timestamp): array
    {
        return [
            'iat' => $timestamp,
            'type' => 'access',
            'username' => $user->getUsername(),
            'userId' => $user->getId(),
            'role' => $user->getRole()?->getId(),
            'roles' => $user->getRoles(),
        ];
    }

    public function getAccessTokenTtl(): int
    {
        return $this->accessTokenTtl;
    }

    public function getRefreshTokenTtl(): int
    {
        return $this->refreshTokenTtl;
    }
}
