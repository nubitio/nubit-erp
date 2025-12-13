<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class RefreshTokenService
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function revokeByToken(string $tokenString): void
    {
        $tokenHash = hash('sha256', $tokenString);
        $storedToken = $this->refreshTokenRepository->findActiveByHash($tokenHash);

        if ($storedToken instanceof RefreshToken) {
            $storedToken->revoke(new DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Refresh token revoked', [
                'jti' => $storedToken->getJti(),
                'user_id' => $storedToken->getUser()->getId(),
            ]);
        }
    }

    public function revokeAllForUser(User $user): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $count = $qb->update(RefreshToken::class, 'rt')
            ->set('rt.revokedAt', ':revokedAt')
            ->where('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('revokedAt', new DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        $this->logger->info('All refresh tokens revoked for user', [
            'user_id' => $user->getId(),
            'count' => $count,
        ]);

        return $count;
    }

    public function cleanExpiredTokens(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $count = $qb->delete(RefreshToken::class, 'rt')
            ->where('rt.expiresAt < :now')
            ->orWhere('rt.revokedAt IS NOT NULL AND rt.revokedAt < :cleanupDate')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('cleanupDate', new DateTimeImmutable('-30 days'))
            ->getQuery()
            ->execute();

        $this->logger->info('Expired refresh tokens cleaned', ['count' => $count]);

        return $count;
    }
}
