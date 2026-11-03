<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ProductImportAccessTest extends TestCase
{
    public function testWebImportAndTemplateUseTheCatalogPermission(): void
    {
        self::assertSame('perm:catalog.create', $this->requiredRole('/api/v1/products/import'));
        self::assertSame('perm:catalog.create', $this->requiredRole('/api/v1/products/import/template'));
    }

    public function testOtherV1RoutesRemainIntegrationOnly(): void
    {
        self::assertSame('ROLE_INTEGRATION', $this->requiredRole('/api/v1/documents'));
        self::assertSame('ROLE_INTEGRATION', $this->requiredRole('/api/v1/products/imported'));
    }

    private function requiredRole(string $path): mixed
    {
        /** @var array{security: array{access_control: list<array{path: string, roles: mixed}>}} $config */
        $config = Yaml::parseFile(__DIR__ . '/../../../config/packages/security.yaml');
        foreach ($config['security']['access_control'] as $rule) {
            if (preg_match('#' . $rule['path'] . '#', $path) === 1) {
                return $rule['roles'];
            }
        }

        self::fail('No access rule matched ' . $path);
    }
}
