<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Tenant;

use PHPUnit\Framework\TestCase;

final class ProcessSecretHardeningTest extends TestCase
{
    public function testProvisionCommandRunnerPassesSecretsThroughScopedEnvironmentNotArgv(): void
    {
        $source = (string) file_get_contents(__DIR__
        . '/../../../src/ControlPlane/Tenant/Service/ProvisionCommandRunner.php');

        self::assertStringContainsString('$env[\'TENANT_ADMIN_PASSWORD\'] = $request->adminPassword', $source);
        self::assertStringContainsString('$env[\'TENANT_DB_PASS\'] = $request->tenantDbPassword', $source);
        self::assertStringContainsString(
            'new Process($command, $this->kernel->getProjectDir(), $env === [] ? null : $env)',
            $source,
        );

        self::assertStringNotContainsString('--admin-password=', $source);
        self::assertStringNotContainsString('--tenant-db-pass=', $source);
    }

    public function testTenantProvisionCommandReadsAdminPasswordFromEnvironment(): void
    {
        $source = (string) file_get_contents(__DIR__
        . '/../../../src/ControlPlane/Tenant/Command/TenantProvisionCommand.php');

        self::assertStringContainsString(
            "'Admin password for initial setup (prefer TENANT_ADMIN_PASSWORD env)'",
            $source,
        );
        self::assertStringContainsString('$_SERVER[\'TENANT_ADMIN_PASSWORD\']', $source);
        self::assertStringContainsString('$_ENV[\'TENANT_ADMIN_PASSWORD\']', $source);
        self::assertStringContainsString("getenv('TENANT_ADMIN_PASSWORD')", $source);
    }

    public function testBackupAndRestoreProcessesUsePasswordEnvironmentNotPasswordArgs(): void
    {
        $source = (string) file_get_contents(__DIR__
        . '/../../../src/ControlPlane/Tenant/Service/TenantBackupService.php');

        self::assertStringContainsString('\'MYSQL_PWD\' => $password', $source);
        self::assertStringContainsString('\'MARIADB_PWD\' => $password', $source);
        self::assertStringContainsString('$this->mysqlPasswordEnv($password)', $source);

        self::assertStringNotContainsString('--password=', $source);
        self::assertStringNotContainsString('-p' . '$password', $source);
    }
}
