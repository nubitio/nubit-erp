<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findActiveByHash(string $tokenHash): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.tokenHash = :hash')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByJti(string $jti): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.jti = :jti')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('jti', $jti)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActiveTokensForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->andWhere('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, RefreshToken>
     */
    public function findExpiredTokens(): array
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, RefreshToken>
     */
    public function findRevokedTokensOlderThan(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.revokedAt IS NOT NULL')
            ->andWhere('rt.revokedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}
