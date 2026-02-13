<?php

declare(strict_types=1);

namespace App\Core\Service;

interface ProvisioningDispatcherInterface
{
    /**
     * @param array<string, string> $inputs
     */
    public function dispatch(array $inputs): void;
}
