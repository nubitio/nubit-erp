<?php

declare(strict_types=1);

namespace App\Tests\Operations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ProductionReliabilityConfigTest extends TestCase
{
    public function testAlertContractCoversIssue405Objectives(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../ops/monitoring/alerts.yml');
        if (!is_array($config) || !is_array($config['groups'] ?? null)) {
            static::fail('alerts.yml must be a Prometheus rule file with groups');
        }

        $group = $config['groups'][0] ?? null;
        if (!is_array($group) || !is_array($group['rules'] ?? null)) {
            static::fail('alerts.yml must contain a rule group');
        }

        $rules = $group['rules'];
        /** @var array<string, array<string, mixed>> $byAlert */
        $byAlert = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || !is_string($rule['alert'] ?? null)) {
                static::fail('Every rule must use Prometheus alert format');
            }
            $byAlert[$rule['alert']] = $rule;
        }

        foreach ([
            'NubitApiAvailabilityLow',
            'NubitApiLatencyP95High',
            'NubitApiServerErrorsHigh',
            'NubitApiClientErrorsHigh',
            'NubitFiscalRecoveryLag',
            'NubitAsyncQueueAgeHigh',
            'NubitWebhookDeliveryLag',
            'NubitBackupFreshnessMissing',
            'NubitTenantHealthDegraded',
        ] as $alert) {
            static::assertArrayHasKey($alert, $byAlert);
            static::assertNotSame('', static::ruleString($byAlert[$alert], 'expr'));
            static::assertContains(static::ruleString($byAlert[$alert], 'labels', 'severity'), ['page', 'ticket']);
            static::assertNotSame('', static::ruleString($byAlert[$alert], 'labels', 'owner'));
            static::assertNotSame('', static::ruleString($byAlert[$alert], 'annotations', 'objective'));
        }

        static::assertStringContainsString('tenant_id', static::ruleString($byAlert['NubitFiscalRecoveryLag'], 'expr'));
        static::assertStringContainsString('tenant_id', static::ruleString(
            $byAlert['NubitBackupFreshnessMissing'],
            'expr',
        ));
    }

    public function testRunbooksAndSmokeGateAreVersioned(): void
    {
        foreach ([
            __DIR__ . '/../../docs/operations/production-reliability.md',
            __DIR__ . '/../../docs/operations/rollback-runbook.md',
            __DIR__ . '/../../docs/operations/restore-runbook.md',
            __DIR__ . '/../../scripts/ops/production-smoke.sh',
            __DIR__ . '/../../scripts/ops/restore-verify.sh',
            __DIR__ . '/../../.github/workflows/rollback-prod.yml',
        ] as $file) {
            static::assertFileExists($file);
        }
    }

    /** @param array<array-key, mixed> $rule */
    private static function ruleString(array $rule, string $key, ?string $nestedKey = null): string
    {
        $value = $rule[$key] ?? null;
        if ($nestedKey !== null) {
            if (!is_array($value)) {
                static::fail(sprintf('Rule field %s must be an object', $key));
            }
            $value = $value[$nestedKey] ?? null;
        }
        if (!is_string($value)) {
            static::fail(sprintf('Rule field %s must be a string', $key));
        }

        return $value;
    }
}
