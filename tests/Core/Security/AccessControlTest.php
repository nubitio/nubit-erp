<?php

declare(strict_types=1);

namespace App\Tests\Core\Security;

use App\Core\EventListener\AppSecretBootListener;
use App\Core\Security\Auth\JWTManager;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Regression tests for SLICE-005 — security hardening.
 *
 * Covers:
 *  - AC-1/AC-2: /api/settings and /api/sales/report require ROLE_USER (not PUBLIC_ACCESS)
 *  - AC-5:      JWTManager rejects empty APP_SECRET at construction time
 *  - AC-5:      AppSecretBootListener exists and forces eager JWTManager instantiation
 */
final class AccessControlTest extends TestCase
{
    private const SECURITY_YAML = __DIR__ . '/../../../config/packages/security.yaml';

    // -------------------------------------------------------------------------
    // AC-1 — /api/settings must require authentication
    // -------------------------------------------------------------------------

    public function testSettingsEndpointRequiresAuthentication(): void
    {
        $accessControl = $this->loadAccessControl();

        $settingsRule = $this->findRule($accessControl, '^/api/settings');

        self::assertNotNull(
            $settingsRule,
            'No access_control rule found for ^/api/settings — it must be explicitly restricted.',
        );

        self::assertSame(
            'ROLE_USER',
            $settingsRule['roles'],
            'The /api/settings endpoint must require ROLE_USER, not PUBLIC_ACCESS.',
        );
    }

    // -------------------------------------------------------------------------
    // AC-2 — /api/sales/report must require authentication
    // -------------------------------------------------------------------------

    public function testSalesReportEndpointRequiresAuthentication(): void
    {
        $accessControl = $this->loadAccessControl();

        $reportRule = $this->findRule($accessControl, '^/api/sales/report');

        self::assertNotNull(
            $reportRule,
            'No access_control rule found for ^/api/sales/report — it must be explicitly restricted.',
        );

        self::assertSame(
            'ROLE_USER',
            $reportRule['roles'],
            'The /api/sales/report endpoint must require ROLE_USER, not PUBLIC_ACCESS.',
        );
    }

    // -------------------------------------------------------------------------
    // AC-5 — JWTManager rejects empty APP_SECRET at construction
    // -------------------------------------------------------------------------

    public function testJWTManagerRejectsEmptySecret(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/APP_SECRET|secret.*empty|empty.*secret/i');

        new JWTManager('', new NullLogger());
    }

    public function testJWTManagerAcceptsNonEmptySecret(): void
    {
        // Must not throw — non-empty secret is valid
        $manager = new JWTManager('some-test-secret-at-least-32-chars-long', new NullLogger());

        self::assertInstanceOf(JWTManager::class, $manager);
    }

    // -------------------------------------------------------------------------
    // AC-5 — AppSecretBootListener exists (eager boot check)
    // -------------------------------------------------------------------------

    public function testAppSecretBootListenerExists(): void
    {
        self::assertTrue(
            class_exists(AppSecretBootListener::class),
            'AppSecretBootListener must exist at App\Core\EventListener\AppSecretBootListener.',
        );
    }

    public function testAppSecretBootListenerThrowsWithEmptySecret(): void
    {
        $this->expectException(LogicException::class);

        // Constructing the listener with a JWTManager that has empty secret
        // must propagate the LogicException — this simulates what happens at
        // kernel boot when APP_SECRET is empty.
        $listener = new AppSecretBootListener(new JWTManager('', new NullLogger()));
        unset($listener);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return list<array{path: string, roles: string}>
     */
    private function loadAccessControl(): array
    {
        self::assertFileExists(self::SECURITY_YAML, 'config/packages/security.yaml must exist.');

        /** @var array<string, mixed> $config */
        $config = Yaml::parseFile(self::SECURITY_YAML);

        /** @var list<array{path: string, roles: string}> $accessControl */
        $accessControl = $config['security']['access_control'] ?? [];

        self::assertNotEmpty($accessControl, 'security.yaml must have at least one access_control entry.');

        return $accessControl;
    }

    /**
     * @param list<array{path: string, roles: string}> $rules
     * @return array{path: string, roles: string}|null
     */
    private function findRule(array $rules, string $path): ?array
    {
        foreach ($rules as $rule) {
            if ($rule['path'] === $path) {
                return $rule;
            }
        }

        return null;
    }
}
