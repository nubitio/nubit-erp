<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\UserNotification;
use App\Repository\UserNotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserNotificationRepository::existsUnreadForUserAndType.
 *
 * The method uses DBAL fetchOne under the hood, so we stub the DBAL
 * Connection only — no ORM QueryBuilder / AbstractQuery involved.
 */
final class UserNotificationRepositoryTest extends TestCase
{
    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a repository wired to a Connection that returns $fetchOneResult
     * from fetchOne().
     */
    private function buildRepository(int|string $fetchOneResult): UserNotificationRepository
    {
        $classMetadata       = $this->createStub(ClassMetadata::class);
        $classMetadata->name = UserNotification::class;

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($fetchOneResult);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->method('getConnection')->willReturn($connection);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(UserNotification::class)
            ->willReturn($entityManager);

        return new UserNotificationRepository($registry);
    }

    // ── existsUnreadForUserAndType() ──────────────────────────────────────────

    public function testReturnsTrueWhenUnreadNotificationExistsWithinWindow(): void
    {
        $result = $this->buildRepository(1)
            ->existsUnreadForUserAndType(42, 'stock_low', new DateTimeImmutable('-24 hours'));

        self::assertTrue($result);
    }

    public function testReturnsFalseWhenNoUnreadNotificationExistsWithinWindow(): void
    {
        $result = $this->buildRepository(0)
            ->existsUnreadForUserAndType(42, 'stock_low', new DateTimeImmutable('-24 hours'));

        self::assertFalse($result);
    }

    public function testReturnsFalseForCountZeroWithDifferentType(): void
    {
        self::assertFalse(
            $this->buildRepository(0)
                ->existsUnreadForUserAndType(99, 'cash_not_opened', new DateTimeImmutable('-1 hour'))
        );
    }

    public function testReturnsTrueForHighCountValue(): void
    {
        self::assertTrue(
            $this->buildRepository(5)
                ->existsUnreadForUserAndType(1, 'stock_low', new DateTimeImmutable('-12 hours'))
        );
    }

    public function testReturnsFalseForStringZeroFromDatabase(): void
    {
        // Doctrine DBAL may return COUNT(*) as string "0" from some drivers
        self::assertFalse(
            $this->buildRepository('0')
                ->existsUnreadForUserAndType(10, 'stock_low', new DateTimeImmutable('-24 hours'))
        );
    }

    public function testReturnsTrueForStringCountFromDatabase(): void
    {
        // Doctrine DBAL may return COUNT(*) as string "3" from some drivers
        self::assertTrue(
            $this->buildRepository('3')
                ->existsUnreadForUserAndType(10, 'stock_low', new DateTimeImmutable('-24 hours'))
        );
    }

    public function testFetchOneReceivesCorrectParameters(): void
    {
        $classMetadata       = $this->createStub(ClassMetadata::class);
        $classMetadata->name = UserNotification::class;

        $userId = 42;
        $type   = 'stock_low';
        $since  = new DateTimeImmutable('-24 hours');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::stringContains('user_notification'),
                self::callback(static function (array $params) use ($userId, $type, $since): bool {
                    return $params[0] === $userId
                        && $params[1] === $type
                        && $params[2] === $since->format('Y-m-d H:i:s');
                }),
            )
            ->willReturn(0);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->method('getConnection')->willReturn($connection);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(UserNotification::class)
            ->willReturn($entityManager);

        $repo = new UserNotificationRepository($registry);
        $repo->existsUnreadForUserAndType($userId, $type, $since);
    }
}
