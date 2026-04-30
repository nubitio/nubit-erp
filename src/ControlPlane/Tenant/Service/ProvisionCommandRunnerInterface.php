<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

interface ProvisionCommandRunnerInterface
{
    /**
     * @return array{successful: bool, exit_code: int, output: string}
     */
    public function run(ProvisionCommandRequest $request): array;
}
