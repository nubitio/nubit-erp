<?php

declare(strict_types=1);

namespace App\Tests\Identity\Entity;

use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    private function makeToken(): RefreshToken
    {
        $role = new Role();
        $user = new User();
        $user->setRole($role);
        $user->setUsername('testuser');

        $token = new RefreshToken();
        $token
            ->setJti('abc-jti-123')
            ->setTokenHash(hash('sha256', 'raw-token'))
            ->setUser($user)
            ->setExpiresAt(new DateTimeImmutable('+1 hour'));

        return $token;
    }

    public function testGettersReturnSetValues(): void
    {
        $token = $this->makeToken();

        self::assertSame('abc-jti-123', $token->getJti());
        self::assertSame(hash('sha256', 'raw-token'), $token->getTokenHash());
        self::assertSame('testuser', $token->getUser()->getUsername());
        self::assertNull($token->getRevokedAt());
        self::assertNull($token->getId()); // not persisted
    }

    public function testCreatedAtIsSetOnConstruct(): void
    {
        $before = new DateTimeImmutable();
        $token = new RefreshToken();
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $token->getCreatedAt());
        self::assertLessThanOrEqual($after, $token->getCreatedAt());
    }

    public function testRevokeSetsFevokedAt(): void
    {
        $token = $this->makeToken();
        $revokedAt = new DateTimeImmutable();

        $result = $token->revoke($revokedAt);

        self::assertSame($token, $result); // fluent
        self::assertSame($revokedAt, $token->getRevokedAt());
    }

    public function testFluentSetters(): void
    {
        $token = new RefreshToken();
        $user = new User();

        self::assertSame($token, $token->setJti('jti'));
        self::assertSame($token, $token->setTokenHash('hash'));
        self::assertSame($token, $token->setUser($user));
        self::assertSame($token, $token->setExpiresAt(new DateTimeImmutable()));
        self::assertSame($token, $token->setRevokedAt(null));
    }

    public function testExpiresAtIsRoundtripped(): void
    {
        $expires = new DateTimeImmutable('2027-01-01 00:00:00');
        $token = new RefreshToken();
        $token->setExpiresAt($expires);

        self::assertSame($expires, $token->getExpiresAt());
    }
}
