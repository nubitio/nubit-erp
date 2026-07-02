<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class ProvisionTenantMessage
{
    public function __construct(
        public int $provisionId,
        public int $accountId,
        public string $tenantName,
        public string $subdomain,
        public string $domain,
        public string $adminPasswordSecret,
    ) {}
}
