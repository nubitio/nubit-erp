<?php

declare(strict_types=1);

namespace App\Tests\Core\Audit;

use App\Core\Audit\AuditLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class AuditLogRepositoryTest extends TestCase
{
    public function testGetAuditLogsReturnsEmptyWhenNotReady(): void
    {
        $repo = $this->createRepository(controlPlaneReady: false);

        $result = $repo->getAuditLogs();

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['total']);
    }

    public function testGetDistinctActionsReturnsEmptyWhenNotReady(): void
    {
        $repo = $this->createRepository(controlPlaneReady: false);

        $this->assertSame([], $repo->getDistinctActions());
    }

    public function testGetAuditLogsIncludesChannelInSelect(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(fn (string $sql) => str_contains($sql, 'a.channel')
                    && str_contains($sql, 'LIMIT ?')
                    && str_contains($sql, 'OFFSET ?')),
                $this->callback(fn (array $params) => $params === [50, 0]),
            )
            ->willReturn([['id' => 1, 'action' => 'test', 'channel' => 'http']]);

        $repo = $this->createRepository(controlPlaneReady: true, connection: $connection);

        $result = $repo->getAuditLogs();

        $this->assertCount(1, $result['rows']);
        $this->assertSame('http', $result['rows'][0]['channel']);
    }

    public function testGetAuditLogsParametrizesLimitAndOffset(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(100);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->anything(),
                $this->callback(fn (array $params) => $params === [25, 50]),
            )
            ->willReturn([]);

        $repo = $this->createRepository(controlPlaneReady: true, connection: $connection);

        $repo->getAuditLogs([], page: 3, limit: 25);
    }

    public function testIsControlPlaneReadyIsCached(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $callCount = 0;
        $cache->method('get')
            ->with('audit_control_plane_ready', $this->anything())
            ->willReturnCallback(function (string $key, callable $callback) use (&$callCount): bool {
                $callCount++;
                if ($callCount === 1) {
                    $item = $this->createMock(ItemInterface::class);
                    $item->expects($this->once())->method('expiresAfter')->with(600);

                    return $callback($item);
                }

                return true;
            });

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->with(['audit_logs'])->willReturn(true);

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchFirstColumn')->willReturn(['action1']);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        $repo = new AuditLogRepository($registry, $cache);

        $repo->getDistinctActions();
        $repo->getDistinctActions();

        $this->assertSame(2, $callCount);
    }

    private function createRepository(bool $controlPlaneReady, ?Connection $connection = null): AuditLogRepository
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')
            ->with('audit_control_plane_ready', $this->anything())
            ->willReturn($controlPlaneReady);

        if ($connection === null) {
            $connection = $this->createStub(Connection::class);
        }

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        return new AuditLogRepository($registry, $cache);
    }
}
