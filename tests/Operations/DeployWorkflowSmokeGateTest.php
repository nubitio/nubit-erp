<?php

declare(strict_types=1);

namespace App\Tests\Operations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class DeployWorkflowSmokeGateTest extends TestCase
{
    public function testEveryDeployCallerUsesTheReusableSmokeGatedWorkflow(): void
    {
        $workflow = file_get_contents(__DIR__ . '/../../.github/workflows/deploy.yml');
        static::assertIsString($workflow);
        static::assertStringContainsString('APP_URL: ${{ secrets.APP_URL }}', $workflow);
        static::assertStringContainsString(': "${APP_URL:?APP_URL is required', $workflow);
        static::assertStringNotContainsString('skipping healthcheck', $workflow);
        static::assertStringNotContainsString('run_healthcheck', $workflow);

        foreach ([
            ['ci-main.yml',        'deploy-dev',        'dev'],
            ['deploy-dev.yml',     'deploy-dev',        'dev'],
            ['deploy-staging.yml', 'deploy-staging',    'stg'],
            ['deploy-prod.yml',    'deploy-production', 'prod'],
            ['rollback-prod.yml',  'rollback',          'prod'],
        ] as [$workflowFile, $jobName, $environment]) {
            $callerWorkflow = Yaml::parseFile(__DIR__ . '/../../.github/workflows/' . $workflowFile);
            static::assertIsArray($callerWorkflow);
            $job = $callerWorkflow['jobs'][$jobName] ?? null;
            static::assertIsArray($job, sprintf('%s must declare %s.', $workflowFile, $jobName));
            static::assertSame('./.github/workflows/deploy.yml', $job['uses'] ?? null);
            static::assertSame($environment, $job['with']['environment'] ?? null);
            static::assertSame('inherit', $job['secrets'] ?? null);
        }
    }
}
