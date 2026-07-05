<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Support;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Infrastructure\Cache\TenantCacheInvalidator;
use App\ControlPlane\Tenant\Infrastructure\Persistence\ControlPlaneSchemaGuard;
use App\ControlPlane\Tenant\Infrastructure\Persistence\DbalTenantDomainRepository;
use App\ControlPlane\Tenant\Infrastructure\Persistence\DbalTenantRepository;
use App\ControlPlane\Tenant\Infrastructure\Persistence\YamlTenantStore;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class TenantServiceFactory extends TestCase
{
    public static function yaml(string $configPath, string $source = 'yaml'): TenantService
    {
        return new self(__METHOD__)->build(
            connection: null,
            configPath: $configPath,
            source: $source,
            bootstrapYaml: true,
        );
    }

    public static function database(
        Connection $connection,
        string $configPath = '',
        string $source = 'db',
        ?LoggerInterface $logger = null,
    ): TenantService {
        return new self(__METHOD__)->build(
            connection: $connection,
            configPath: $configPath !== ''
                ? $configPath
                : sys_get_temp_dir() . '/tenants-fallback-' . bin2hex(random_bytes(4)) . '.yml',
            source: $source,
            logger: $logger,
        );
    }

    private function build(
        ?Connection $connection,
        string $configPath,
        string $source,
        bool $bootstrapYaml = false,
        ?LoggerInterface $logger = null,
    ): TenantService {
        /** @var ConnectionRegistry&MockObject $registry */
        $registry = $this->createMock(ConnectionRegistry::class);
        if ($connection !== null) {
            $registry->method('getConnection')->with('control_plane')->willReturn($connection);
        }

        $logger ??= $this->createStub(LoggerInterface::class);
        $schemaGuard = new ControlPlaneSchemaGuard($registry, $logger);
        $yamlStore = new YamlTenantStore($configPath, $bootstrapYaml);
        $tenantRepository = new DbalTenantRepository($schemaGuard, $yamlStore, $logger, $source);
        $domainRepository = new DbalTenantDomainRepository($schemaGuard);
        $cache = self::inMemoryCache();
        $featureCache = $this->createMock(TagAwareCacheInterface::class);
        $invalidator = new TenantCacheInvalidator($cache, $featureCache, $domainRepository);

        return new TenantService(
            tenantRepository: $tenantRepository,
            domainRepository: $domainRepository,
            cacheInvalidator: $invalidator,
            cache: $cache,
            tenantsSource: $source,
        );
    }

    private static function inMemoryCache(): CacheInterface
    {
        /** @var array<string, mixed> $store */
        $store = [];

        return new class($store) implements CacheInterface {
            public function __construct(
                private array &$store,
            ) {}

            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                if (!array_key_exists($key, $this->store)) {
                    $item = new class implements ItemInterface {
                        private int $expiry = 0;

                        public function getKey(): string
                        {
                            return '';
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

                        public function tag(mixed $tags): static
                        {
                            return $this;
                        }

                        public function getMetadata(): array
                        {
                            return [];
                        }
                    };

                    $this->store[$key] = $callback($item);
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
