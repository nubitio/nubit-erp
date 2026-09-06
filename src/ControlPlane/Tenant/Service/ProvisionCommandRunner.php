<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

final readonly class ProvisionCommandRunner implements ProvisionCommandRunnerInterface
{
    public function __construct(
        private KernelInterface $kernel,
    ) {}

    /**
     * @return array{successful:bool,exit_code:int,output:string}
     */
    public function run(ProvisionCommandRequest $request): array
    {
        $tenant = trim($request->tenant);
        if ($tenant === '') {
            return [
                'successful' => false,
                'exit_code' => 1,
                'output' => 'Tenant slug is required.',
            ];
        }

        $displayName = trim($request->tenantDisplayName);
        if ($displayName === '') {
            $displayName = $tenant;
        }

        $command = [
            $this->resolvePhpExecutable(),
            'bin/console',
            'app:tenant:provision',
            $tenant,
            '--no-interaction',
        ];

        $domain = trim($request->domain);
        if ($domain !== '') {
            $command[] = '--domain=' . $domain;
        }

        if ($request->createUser) {
            $command[] = '--create-user';
        }

        if ($request->setup) {
            $command[] = '--setup';
            $command[] = '--admin-user=' . trim($request->adminUser);
            if (trim($request->adminPassword) !== '') {
                $env['TENANT_ADMIN_PASSWORD'] = $request->adminPassword;
            }
            $command[] = '--company-ruc=' . trim($request->companyRuc);
            $command[] = '--company-name=' . $displayName;
            $command[] = '--company-trade=' . $displayName;
            $command[] = '--company-address=' . trim($request->companyAddress);
            $command[] = '--company-phone=' . trim($request->companyPhone);
            $command[] = '--branch-name=' . trim($request->branchName);
            $command[] = '--branch-address=' . trim($request->branchAddress);
            $command[] = '--branch-phone=' . trim($request->branchPhone);
        } elseif (trim($request->adminUser) !== '') {
            $command[] = '--admin-user=' . trim($request->adminUser);
        }

        $provisioningUrl = trim($request->provisioningUrl);
        if ($provisioningUrl !== '') {
            $command[] = '--provisioning-url=' . $provisioningUrl;
        }

        if ($request->provisionId > 0) {
            $command[] = '--provision-id=' . $request->provisionId;
        }

        $vertical = trim($request->vertical);
        if ($vertical !== '') {
            $command[] = '--vertical=' . $vertical;
        }

        $env ??= [];
        if (trim($request->tenantDbPassword) !== '') {
            $env['TENANT_DB_PASS'] = $request->tenantDbPassword;
        }

        $process = new Process($command, $this->kernel->getProjectDir(), $env === [] ? null : $env);
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

    private function resolvePhpExecutable(): string
    {
        $binary = trim((string) (\defined('PHP_BINARY') ? PHP_BINARY : ''));
        if ($binary === '') {
            return 'php';
        }

        $basename = strtolower(basename($binary));
        if (str_contains($basename, 'php-fpm')) {
            return 'php';
        }

        return $binary;
    }
}
