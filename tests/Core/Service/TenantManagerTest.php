<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\Core\Service\TenantManager;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class TenantManagerTest extends TestCase
{
    public function testConstructorRequiresConfigPathWhenYamlIsEnabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenants config path is required');

        new TenantManager(
            '',
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache()
        );
    }

    public function testYamlCrudFlow(): void
    {
        $configPath = $this->tempPath('tenants.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: default }\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache()
        );

        self::assertCount(1, $manager->getTenants());

        $manager->addTenant([
            'name' => 'globex',
            'connection' => 'globex_conn',
        ]);

        $globex = $manager->getTenantByName('globex');
        self::assertNotNull($globex);
        self::assertSame('globex_conn', $globex['connection']);
        self::assertSame(2, $globex['id']);

        $manager->updateTenant(2, [
            'name' => 'globex-new',
            'connection' => 'globex_v2',
        ]);
        $updated = $manager->getTenant(2);
        self::assertNotNull($updated);
        self::assertSame('globex-new', $updated['name']);
        self::assertSame('globex_v2', $updated['connection']);

        $manager->deleteTenant(2);
        self::assertNull($manager->getTenant(2));
        self::assertNull($manager->getTenantByDomain('tenant.example.test'));
    }

    public function testYamlCrudValidations(): void
    {
        $configPath = $this->tempPath('tenants-validate.yml');
        file_put_contents($configPath, "- { id: 1, name: acme, connection: default }\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name and connection are required');
        $manager->addTenant(['name' => 'acme']);
    }

    public function testGetTenantByNameReturnsNullWhenBlank(): void
    {
        $configPath = $this->tempPath('tenants-empty.yml');
        file_put_contents($configPath, "[]\n");

        $manager = new TenantManager(
            $configPath,
            'yaml',
            $this->createMock(ConnectionRegistry::class),
            $this->createMock(LoggerInterface::class),
            $this->inMemoryCache()
        );

        self::assertNull($manager->getTenantByName('   '));
        self::assertSame([], $manager->getDeletedTenants());
    }

    private function tempPath(string $file): string
    {
        $dir = sys_get_temp_dir() . '/efact-tests-' . bin2hex(random_bytes(4));
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $file;
    }

    private function inMemoryCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var array<string, mixed> */
            private array $store = [];

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
