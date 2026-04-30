<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\RefreshTokenService;
use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\User;
use App\Identity\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RefreshTokenServiceTest extends TestCase
{
    private function makeService(
        RefreshTokenRepository $repo,
        EntityManagerInterface $em,
        ?LoggerInterface $logger = null,
    ): RefreshTokenService {
        return new RefreshTokenService(
            $repo,
            $em,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    // ── revokeByToken ─────────────────────────────────────────────────────────

    public function testRevokeByTokenRevokesFoundToken(): void
    {
        $user = new User();
        $user->setUsername('juan');

        $token = new RefreshToken();
        $token->setJti('jti-123')->setUser($user)
            ->setTokenHash('h')
            ->setExpiresAt(new DateTimeImmutable('+1h'));

        $repo = $this->createStub(RefreshTokenRepository::class);
        $repo->method('findActiveByHash')->willReturn($token);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Refresh token revoked', self::arrayHasKey('jti'));

        $this->makeService($repo, $em, $logger)->revokeByToken('raw-token');

        self::assertNotNull($token->getRevokedAt());
    }

    public function testRevokeByTokenDoesNothingWhenTokenNotFound(): void
    {
        $repo = $this->createStub(RefreshTokenRepository::class);
        $repo->method('findActiveByHash')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->makeService($repo, $em)->revokeByToken('nonexistent-token');
    }

    public function testRevokeByTokenHashesTokenBeforeLookup(): void
    {
        $rawToken = 'raw-token-value';
        $expectedHash = hash('sha256', $rawToken);

        $repo = $this->createMock(RefreshTokenRepository::class);
        $repo->expects(self::once())
            ->method('findActiveByHash')
            ->with($expectedHash)
            ->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);

        $this->makeService($repo, $em)->revokeByToken($rawToken);
    }

    // ── revokeAllForUser ──────────────────────────────────────────────────────

    public function testRevokeAllForUserExecutesQueryBuilderUpdate(): void
    {
        $user = new User();

        $query = $this->createStub(Query::class);
        $query->method('execute')->willReturn(3);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createStub(RefreshTokenRepository::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('All refresh tokens revoked for user', self::arrayHasKey('count'));

        $count = $this->makeService($repo, $em, $logger)->revokeAllForUser($user);
        self::assertSame(3, $count);
    }

    // ── cleanExpiredTokens ────────────────────────────────────────────────────

    public function testCleanExpiredTokensExecutesDeleteQuery(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('execute')->willReturn(12);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $repo = $this->createStub(RefreshTokenRepository::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('Expired refresh tokens cleaned', ['count' => 12]);

        $count = $this->makeService($repo, $em, $logger)->cleanExpiredTokens();
        self::assertSame(12, $count);
    }
}
