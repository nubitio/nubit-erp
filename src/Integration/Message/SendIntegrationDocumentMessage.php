<?php

declare(strict_types=1);

namespace App\Integration\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Submit a queued integration document to SUNAT. The tenant is also carried on
 * the platform TenantStamp; this field is for logging parity with the rest of
 * the Billing messages.
 */
#[AsMessage(transport: 'async')]
final readonly class SendIntegrationDocumentMessage
{
    public function __construct(
        public string $integrationDocumentId,
        private string $tenantId,
    ) {}

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
