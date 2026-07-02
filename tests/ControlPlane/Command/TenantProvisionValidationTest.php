<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Command;

use App\ControlPlane\Tenant\Command\TenantProvisionCommand;
use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use App\ControlPlane\Tenant\Port\InitialTenantSetupPortInterface;
use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Cache\CacheInterface;

final class TenantProvisionValidationTest extends TestCase
{
    private const string PATTERN = '/^[a-z0-9][a-z0-9_-]{1,61}[a-z0-9]$/i';

    #[DataProvider('validTenantNames')]
    public function testAcceptsValidTenantNames(string $name): void
    {
        $this->assertMatchesRegularExpression(self::PATTERN, $name);
    }

    #[DataProvider('invalidTenantNames')]
    public function testRejectsInvalidTenantNames(string $name): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PATTERN, $name);
    }

    public function testProvisionCommandSqlMatchesControlPlaneSchema(): void
    {
        $source = (string) file_get_contents(__DIR__
        . '/../../../src/ControlPlane/Tenant/Command/TenantProvisionCommand.php');

        self::assertStringContainsString(
            'INSERT INTO tenants (name, connection, status, created_at, updated_at)',
            $source,
        );
        self::assertStringContainsString('[$tenant, $tenant, \'active\']', $source);
        self::assertStringContainsString('INSERT INTO tenant_domains (tenant_id, domain, is_primary)', $source);
        self::assertStringContainsString("status = 'running'", $source);
        self::assertStringContainsString("status = 'failed', error_message = ?, completed_at = NOW()", $source);
        self::assertStringContainsString('completed_at = NOW()', $source);

        self::assertStringNotContainsString('primary_domain', $source);
        self::assertStringNotContainsString('finished_at', $source);
        self::assertStringNotContainsString("status = 'provisioning'", $source);
    }

    public function testProvisionFailureMarksProvisionFailedWithCompletedAt(): void
    {
        $statements = [];
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane
            ->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $command = $this->createCommand($controlPlane, 'unsupported-driver://localhost/provisioning');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'tenant' => 'acme',
            '--provision-id' => '42',
        ]);

        self::assertSame(Command::FAILURE, $exitCode, $tester->getDisplay());
        self::assertCount(2, $statements);
        self::assertSame(
            "UPDATE tenant_provisions SET status = 'running', started_at = NOW() WHERE id = ?",
            $statements[0][0],
        );
        self::assertSame([42], $statements[0][1]);
        self::assertSame(
            "UPDATE tenant_provisions SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?",
            $statements[1][0],
        );
        self::assertNotSame('', $statements[1][1][0]);
        self::assertSame(42, $statements[1][1][1]);
    }

    public function testPrimaryDomainIsPersistedInTenantDomains(): void
    {
        $statements = [];
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with('SELECT id, tenant_id FROM tenant_domains WHERE domain = ?', ['acme.example.test'])
            ->willReturn(false);
        $controlPlane
            ->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $this->invokeUpsertPrimaryDomain($controlPlane, 7, ' Acme.Example.Test ');

        self::assertSame('UPDATE tenant_domains SET is_primary = 0 WHERE tenant_id = ?', $statements[0][0]);
        self::assertSame([7], $statements[0][1]);
        self::assertSame(
            'INSERT INTO tenant_domains (tenant_id, domain, is_primary) VALUES (?, ?, 1)',
            $statements[1][0],
        );
        self::assertSame([7, 'acme.example.test'], $statements[1][1]);
    }

    public function testSetupAdminPasswordPrefersScopedEnvironmentOverArgvOption(): void
    {
        $command = $this->createCommandForPrivateMethodTests();
        $input = new ArrayInput(['--admin-password' => 'argv-secret']);
        $input->bind($command->getDefinition());

        $previousServer = $_SERVER['TENANT_ADMIN_PASSWORD'] ?? null;
        $previousEnv = $_ENV['TENANT_ADMIN_PASSWORD'] ?? null;
        $previousProcessEnv = getenv('TENANT_ADMIN_PASSWORD');

        $_SERVER['TENANT_ADMIN_PASSWORD'] = 'env-secret';
        $_ENV['TENANT_ADMIN_PASSWORD'] = 'env-secret';
        putenv('TENANT_ADMIN_PASSWORD=env-secret');

        try {
            $method = new \ReflectionMethod($command, 'resolveAdminPassword');

            self::assertSame('env-secret', $method->invoke($command, $input));
        } finally {
            $this->restoreTenantAdminPasswordEnv($previousServer, $previousEnv, $previousProcessEnv);
        }
    }

    public function testDomainUniquenessFailureDoesNotCorruptCurrentPrimaryDomain(): void
    {
        $controlPlane = $this->createMock(Connection::class);
        $controlPlane
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with('SELECT id, tenant_id FROM tenant_domains WHERE domain = ?', ['used.example.test'])
            ->willReturn(['id' => 15, 'tenant_id' => 99]);
        $controlPlane->expects(self::never())->method('executeStatement');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Domain "used.example.test" is already registered to another tenant.');

        $this->invokeUpsertPrimaryDomain($controlPlane, 7, 'used.example.test');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validTenantNames(): iterable
    {
        yield 'simple' => ['acme'];
        yield 'with-hyphen' => ['acme-corp'];
        yield 'with-underscore' => ['acme_corp'];
        yield 'numeric' => ['tenant123'];
        yield 'min-length' => ['abc'];
        yield 'max-length' => [str_repeat('a', 63)];
        yield 'mixed' => ['my-tenant_2024'];
        yield 'starts-with-number' => ['1acme'];
        yield 'all-numbers' => ['123'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTenantNames(): iterable
    {
        yield 'too-short' => ['ab'];
        yield 'single-char' => ['a'];
        yield 'empty' => [''];
        yield 'too-long' => [str_repeat('a', 64)];
        yield 'starts-with-hyphen' => ['-acme'];
        yield 'ends-with-hyphen' => ['acme-'];
        yield 'starts-with-underscore' => ['_acme'];
        yield 'ends-with-underscore' => ['acme_'];
        yield 'has-dot' => ['acme.corp'];
        yield 'has-space' => ['acme corp'];
        yield 'sql-injection-attempt' => ['acme; DROP DATABASE'];
        yield 'backtick-injection' => ['acme`--'];
        yield 'quote-injection' => ["acme'OR'1"];
        yield 'special-chars' => ['acme$corp'];
        yield 'path-traversal' => ['../etc/passwd'];
        yield 'unicode' => ['ácme'];
    }

    private function createCommand(
        Connection $controlPlane,
        string $provisioningDatabaseUrl = 'unsupported-driver://localhost/provisioning',
    ): TenantProvisionCommand {
        $connectionRegistry = $this->createMock(ConnectionRegistry::class);
        $connectionRegistry
            ->expects(self::once())
            ->method('getConnection')
            ->with('control_plane')
            ->willReturn($controlPlane);

        return new TenantProvisionCommand(
            $connectionRegistry,
            $this->createStub(TenantConnectionSwitcher::class),
            $this->createStub(CacheInterface::class),
            $this->createStub(InitialTenantSetupPortInterface::class),
            $this->createStub(ElectronicBillingCompanyPortInterface::class),
            'tenant-pass',
            $provisioningDatabaseUrl,
        );
    }

    private function invokeUpsertPrimaryDomain(Connection $controlPlane, int $tenantId, string $domain): void
    {
        $command = $this->createCommandForPrivateMethodTests();
        $method = new \ReflectionMethod($command, 'upsertPrimaryDomain');
        $method->invoke($command, $controlPlane, $tenantId, $domain);
    }

    private function createCommandForPrivateMethodTests(): TenantProvisionCommand
    {
        return new TenantProvisionCommand(
            $this->createStub(ConnectionRegistry::class),
            $this->createStub(TenantConnectionSwitcher::class),
            $this->createStub(CacheInterface::class),
            $this->createStub(InitialTenantSetupPortInterface::class),
            $this->createStub(ElectronicBillingCompanyPortInterface::class),
            'tenant-pass',
            'unsupported-driver://localhost/provisioning',
        );
    }

    private function restoreTenantAdminPasswordEnv(?string $server, ?string $env, string|false $processEnv): void
    {
        if ($server === null) {
            unset($_SERVER['TENANT_ADMIN_PASSWORD']);
        } else {
            $_SERVER['TENANT_ADMIN_PASSWORD'] = $server;
        }

        if ($env === null) {
            unset($_ENV['TENANT_ADMIN_PASSWORD']);
        } else {
            $_ENV['TENANT_ADMIN_PASSWORD'] = $env;
        }

        if ($processEnv === false) {
            putenv('TENANT_ADMIN_PASSWORD');
        } else {
            putenv('TENANT_ADMIN_PASSWORD=' . $processEnv);
        }
    }
}
