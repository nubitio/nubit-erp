<?php

declare(strict_types=1);

namespace App\Onboarding\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

final readonly class TenantProvisioningService
{
    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @return array{successful:bool,exit_code:int,output:string}
     */
    public function run(
        int $provisionId,
        string $tenantName,
        string $subdomain,
        string $domain,
        string $adminPassword,
        string $tenantDbPassword,
    ): array {
        $process = new Process([
            PHP_BINARY,
            'bin/console',
            'app:tenant:provision',
            $subdomain,
            '--no-interaction',
            '--create-user',
            '--domain=' . $domain,
            '--setup',
            '--admin-user=admin',
            '--company-ruc=20000000001',
            '--company-name=' . $tenantName,
            '--company-trade=' . $tenantName,
            '--company-address=Pendiente por completar',
            '--company-phone=999999999',
            '--branch-name=Principal',
            '--branch-address=Pendiente por completar',
            '--branch-phone=999999999',
            '--provision-id=' . $provisionId,
        ], $this->kernel->getProjectDir(), [
            'TENANT_ADMIN_PASS' => $adminPassword,
            'TENANT_DB_PASS' => $tenantDbPassword,
        ]);

        $process->setTimeout(null);
        $process->run();

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $combinedOutput = trim($stdout . "\n" . $stderr);

        return [
            'successful' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => $combinedOutput,
        ];
    }
}
