<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\Efact\Doctrine\DBAL\TenantConnection;
use App\ControlPlane\Tenant\Service\TenantHealthChecker;
use App\ControlPlane\Tenant\Service\TenantManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantHealthCheckerTest extends TestCase
{
    public function testCheckReturnsHealthyWhenTenantConnectionAndSchemaAreValid(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['user', 'config', 'branch']);

        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::once())->method('switchTenantParams');
        $tenantConnection->expects(self::once())->method('executeQuery')->with('SELECT 1');
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantManagerWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $this->createMock(LoggerInterface::class)
        );

        $result = $checker->check('acme');

        self::assertTrue($result['healthy']);
        self::assertTrue($result['checks']['control_plane']['ok']);
        self::assertTrue($result['checks']['connection']['ok']);
        self::assertTrue($result['checks']['schema']['ok']);
    }

    public function testCheckReturnsUnhealthyWhenTenantDoesNotExist(): void
    {
        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::never())->method('executeQuery');

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantManagerWithConfig("[]\n"),
            $this->createMock(LoggerInterface::class)
        );

        $result = $checker->check('missing');

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['control_plane']['ok']);
        self::assertFalse($result['checks']['connection']['ok']);
        self::assertArrayNotHasKey('schema', $result['checks']);
    }

    public function testCheckReturnsUnhealthyWhenConnectionFails(): void
    {
        $tenantConnection = $this->createMock(TenantConnection::class);
        $tenantConnection->expects(self::once())->method('switchTenantParams');
        $tenantConnection->method('executeQuery')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantManagerWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $logger
        );

        $result = $checker->check('acme');

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['connection']['ok']);
        self::assertArrayNotHasKey('schema', $result['checks']);
    }

    public function testCheckReportsMissingSchemaTables(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['user', 'branch']);

        $tenantConnection = $this->createStub(TenantConnection::class);
        $tenantConnection->method('createSchemaManager')->willReturn($schema);

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantManagerWithConfig("- { id: 1, name: acme, connection: acme_db }\n"),
            $this->createMock(LoggerInterface::class)
        );

        $result = $checker->check('acme');

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['schema']['ok']);
        self::assertStringContainsString('config', $result['checks']['schema']['message']);
    }

    public function testCheckHandlesControlPlaneLookupException(): void
    {
        $tenantConnection = $this->createStub(TenantConnection::class);
        $tenantConnection->method('executeQuery')->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        $checker = new TenantHealthChecker(
            $this->tenantRegistry($tenantConnection),
            $this->tenantManagerWithConfig("[invalid-yaml\n"),
            $logger
        );

        $result = $checker->check('acme');

        self::assertFalse($result['healthy']);
        self::assertSame('Control plane lookup failed', $result['checks']['control_plane']['message']);
        self::assertSame('Database connection failed', $result['checks']['connection']['message']);
    }

    private function tenantRegistry(TenantConnection $tenantConnection): ConnectionRegistry
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('tenant')->willReturn($tenantConnection);

        return $registry;
    }

    private function tenantManagerWithConfig(string $yamlContent): TenantManager
    {
        $path = sys_get_temp_dir() . '/efact-tenant-health-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($path, $yamlContent);

        return new TenantManager(
            $path,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache(),
            $this->createMock(TagAwareCacheInterface::class)
        );
    }

    private function inMemoryCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            /**
             * @param array<string, mixed>|null $metadata
             */
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                if (!array_key_exists($key, $this->store)) {
                    $item = new class () implements ItemInterface {
                        public function getKey(): string
                        {
                            return 'cache-item';
                        }

                        public function get(): mixed
                        {
                            return null;
                        }

                        public function isHit(): bool
                        {
                            return false;
                        }

                        public function set(mixed $value): static
                        {
                            return $this;
                        }

                        public function expiresAt(?\DateTimeInterface $expiration): static
                        {
                            return $this;
                        }

                        public function expiresAfter(int|\DateInterval|null $time): static
                        {
                            return $this;
                        }

                        public function tag(string|iterable $tags): static
                        {
                            return $this;
                        }

                        /**
                         * @return array<string, mixed>
                         */
                        public function getMetadata(): array
                        {
                            return [];
                        }
                    };
                    $this->store[$key] = $callback($item, true);
                }

                return $this->store[$key];
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }
        };
    }
}
