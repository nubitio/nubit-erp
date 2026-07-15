<?php

declare(strict_types=1);

namespace App\Tests\Identity\Repository;

use App\Identity\Entity\UserNotification;
use App\Identity\Repository\UserNotificationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserNotificationRepository::existsUnreadForUserAndType.
 *
 * The method uses DQL and returns a scalar count.
 */
final class UserNotificationRepositoryTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a repository wired to a DQL query that returns $count.
     */
    private function buildRepository(int|string $count): UserNotificationRepository
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->name = UserNotification::class;

        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')->willReturn($count);

        $query->method('setParameter')->willReturnSelf();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->method('createQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(UserNotification::class)->willReturn($entityManager);

        return new UserNotificationRepository($registry);
    }

    // ── existsUnreadForUserAndType() ──────────────────────────────────────────

    public function testReturnsTrueWhenUnreadNotificationExistsWithinWindow(): void
    {
        $result = $this->buildRepository(1)->existsUnreadForUserAndType(
            42,
            'stock_low',
            new DateTimeImmutable('-24 hours'),
        );

        self::assertTrue($result);
    }

    public function testReturnsFalseWhenNoUnreadNotificationExistsWithinWindow(): void
    {
        $result = $this->buildRepository(0)->existsUnreadForUserAndType(
            42,
            'stock_low',
            new DateTimeImmutable('-24 hours'),
        );

        self::assertFalse($result);
    }

    public function testReturnsFalseForCountZeroWithDifferentType(): void
    {
        self::assertFalse($this->buildRepository(0)->existsUnreadForUserAndType(
            99,
            'cash_not_opened',
            new DateTimeImmutable('-1 hour'),
        ));
    }

    public function testReturnsTrueForHighCountValue(): void
    {
        self::assertTrue($this->buildRepository(5)->existsUnreadForUserAndType(
            1,
            'stock_low',
            new DateTimeImmutable('-12 hours'),
        ));
    }

    public function testReturnsFalseForStringZeroFromDatabase(): void
    {
        // Doctrine DBAL may return COUNT(*) as string "0" from some drivers
        self::assertFalse($this->buildRepository('0')->existsUnreadForUserAndType(
            10,
            'stock_low',
            new DateTimeImmutable('-24 hours'),
        ));
    }

    public function testReturnsTrueForStringCountFromDatabase(): void
    {
        // Doctrine DBAL may return COUNT(*) as string "3" from some drivers
        self::assertTrue($this->buildRepository('3')->existsUnreadForUserAndType(
            10,
            'stock_low',
            new DateTimeImmutable('-24 hours'),
        ));
    }

    public function testDqlQueryReceivesCorrectParameters(): void
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->name = UserNotification::class;

        $userId = 42;
        $type = 'stock_low';
        $since = new DateTimeImmutable('-24 hours');

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('0');

        $query
            ->expects(self::exactly(3))
            ->method('setParameter')
            ->willReturnCallback(static function (string $name, mixed $value) use ($query, $userId, $type, $since): Query {
                self::assertSame(match ($name) {
                    'userId' => $userId,
                    'type' => $type,
                    'since' => $since,
                    default => throw new \LogicException('Unexpected query parameter.'),
                }, $value);

                return $query;
            });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->method('createQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(UserNotification::class)->willReturn($entityManager);

        $repo = new UserNotificationRepository($registry);
        $repo->existsUnreadForUserAndType($userId, $type, $since);
    }

    public function testMarkAllUnreadAsReadExecutesBulkDqlUpdate(): void
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->name = UserNotification::class;
        $readAt = new DateTimeImmutable();

        $query = $this->createMock(Query::class);
        $query
            ->expects(self::exactly(2))
            ->method('setParameter')
            ->willReturnCallback(static function (string $name, mixed $value) use ($query, $readAt): Query {
                self::assertSame(match ($name) {
                    'readAt' => $readAt,
                    'userId' => 42,
                    default => throw new \LogicException('Unexpected query parameter.'),
                }, $value);

                return $query;
            });
        $query->expects(self::once())->method('execute');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager
            ->expects(self::once())
            ->method('createQuery')
            ->with(self::stringContains('UPDATE App\\Identity\\Entity\\UserNotification'))
            ->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(UserNotification::class)->willReturn($entityManager);

        (new UserNotificationRepository($registry))->markAllUnreadAsRead(42, $readAt);
    }
}
