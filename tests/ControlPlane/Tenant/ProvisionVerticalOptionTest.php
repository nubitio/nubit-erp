<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Tenant;

use PHPUnit\Framework\TestCase;

use function file_get_contents;

/**
 * NB-9: `app:tenant:provision --vertical=<key>` must flow through to
 * `tenant:apply-preset` so a tenant can be provisioned as retail/pharmacy
 * without touching the DB by hand. Source-level assertions in the style of
 * ProcessSecretHardeningTest — the command building lives inside methods that
 * spawn real processes and are not unit-testable directly.
 */
final class ProvisionVerticalOptionTest extends TestCase
{
    public function testProvisionCommandExposesVerticalOptionAndAppliesThePreset(): void
    {
        $source = $this->read('src/ControlPlane/Tenant/Command/TenantProvisionCommand.php');

        self::assertStringContainsString("->addOption(\n                'vertical',", $source);
        self::assertStringContainsString("'tenant:apply-preset',", $source);
        self::assertStringContainsString("'--tenant=' . \$tenant,", $source);
    }

    public function testProvisionCommandRunnerForwardsTheVerticalFlag(): void
    {
        $source = $this->read('src/ControlPlane/Tenant/Service/ProvisionCommandRunner.php');

        self::assertStringContainsString("\$vertical = trim(\$request->vertical);", $source);
        self::assertStringContainsString("\$command[] = '--vertical=' . \$vertical;", $source);
    }

    public function testProvisionRequestCarriesTheVertical(): void
    {
        $source = $this->read('src/ControlPlane/Tenant/Service/ProvisionCommandRequest.php');

        self::assertStringContainsString('public string $vertical = \'\',', $source);
    }

    private function read(string $relativePath): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../' . $relativePath);
    }
}
