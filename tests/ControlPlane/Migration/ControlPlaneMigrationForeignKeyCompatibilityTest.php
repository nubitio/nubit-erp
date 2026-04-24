<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Migration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ControlPlaneMigrationForeignKeyCompatibilityTest extends TestCase
{
    public function testTenantCertificatesTenantForeignKeyUsesSignedTenantId(): void
    {
        $tenantsMigration = $this->migrationSql('Version20260210120000.php');
        self::assertMatchesRegularExpression(
            '/CREATE TABLE tenants \(id INT AUTO_INCREMENT NOT NULL,.*PRIMARY KEY\(id\)/i',
            $tenantsMigration,
            'Control-plane tenants.id must remain a signed INT primary key.',
        );

        $certificateMigration = $this->migrationSql('Version20260424142000.php');

        self::assertStringContainsString('tenant_id INT NOT NULL', $certificateMigration);
        self::assertStringNotContainsString('tenant_id INT UNSIGNED', $certificateMigration);
        self::assertMatchesRegularExpression(
            '/CONSTRAINT FK_TENANT_CERT_TENANT FOREIGN KEY \(tenant_id\) REFERENCES tenants \(id\) ON DELETE CASCADE/i',
            $certificateMigration,
            'tenant_certificates.tenant_id must match tenants.id signedness before adding the FK.',
        );
    }

    /**
     * @param non-empty-string $migrationFile
     * @param non-empty-string $signedTenantColumnPattern
     * @param non-empty-string $foreignKeyPattern
     */
    #[DataProvider('tenantForeignKeyCompatibilityProvider')]
    public function testTenantForeignKeyColumnsAreSignedBeforeConstraintsAreAdded(
        string $tableName,
        string $migrationFile,
        string $signedTenantColumnPattern,
        string $foreignKeyPattern,
    ): void {
        $sql = $this->migrationSql($migrationFile);

        self::assertMatchesRegularExpression(
            $signedTenantColumnPattern,
            $sql,
            sprintf('%s.tenant_id must be declared or normalized as signed INT to match tenants.id.', $tableName),
        );
        self::assertMatchesRegularExpression(
            $foreignKeyPattern,
            $sql,
            sprintf('%s must keep its tenant_id foreign key to tenants(id).', $tableName),
        );
    }

    /**
     * @return iterable<string, array{0: non-empty-string, 1: non-empty-string, 2: non-empty-string, 3: non-empty-string}>
     */
    public static function tenantForeignKeyCompatibilityProvider(): iterable
    {
        yield 'tenant_domains' => [
            'tenant_domains',
            'Version20260210120000.php',
            '/CREATE TABLE tenant_domains \(.*tenant_id INT NOT NULL.*CONSTRAINT FK_TENANT_DOMAIN_TENANT/s',
            '/CONSTRAINT FK_TENANT_DOMAIN_TENANT FOREIGN KEY \(tenant_id\) REFERENCES tenants \(id\) ON DELETE CASCADE/i',
        ];

        yield 'tenant_provisions' => [
            'tenant_provisions',
            'Version20260227181000.php',
            '/ALTER TABLE tenant_provisions MODIFY tenant_id INT NULL, MODIFY account_id INT UNSIGNED NULL/i',
            '/ALTER TABLE tenant_provisions ADD CONSTRAINT fk_tenant_provisions_tenant FOREIGN KEY \(tenant_id\) REFERENCES tenants \(id\) ON DELETE SET NULL/i',
        ];

        yield 'subscriptions' => [
            'subscriptions',
            'Version20260227181000.php',
            '/ALTER TABLE subscriptions MODIFY tenant_id INT NULL/i',
            '/ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_tenant FOREIGN KEY \(tenant_id\) REFERENCES tenants \(id\) ON DELETE CASCADE/i',
        ];

        yield 'tenant_certificates' => [
            'tenant_certificates',
            'Version20260424142000.php',
            '/CREATE TABLE tenant_certificates \(.*tenant_id INT NOT NULL.*CONSTRAINT FK_TENANT_CERT_TENANT/s',
            '/CONSTRAINT FK_TENANT_CERT_TENANT FOREIGN KEY \(tenant_id\) REFERENCES tenants \(id\) ON DELETE CASCADE/i',
        ];
    }

    private function migrationSql(string $fileName): string
    {
        $path = __DIR__ . '/../../../migrations_control_plane/' . $fileName;
        self::assertFileExists($path);

        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? $contents;
    }
}
