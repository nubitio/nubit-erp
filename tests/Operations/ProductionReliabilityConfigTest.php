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
        $rules = $config['groups'][0]['rules'] ?? [];
        $byId = [];
        foreach ($rules as $rule) {
            $byId[$rule['id']] = $rule;
        }

        foreach ([
            'api-availability',
            'api-latency-p95',
            'api-server-errors',
            'api-client-errors',
            'fiscal-recovery-lag',
            'async-queue-age',
            'webhook-delivery-lag',
            'backup-freshness',
            'tenant-health',
        ] as $id) {
            self::assertArrayHasKey($id, $byId);
            self::assertNotSame('', $byId[$id]['expr']);
            self::assertContains($byId[$id]['severity'], ['page', 'ticket']);
            self::assertNotSame('', $byId[$id]['owner']);
        }
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
            self::assertFileExists($file);
        }
    }
}
