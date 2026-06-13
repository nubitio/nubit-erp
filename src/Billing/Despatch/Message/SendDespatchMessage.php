<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class SendDespatchMessage
{
    public function __construct(
        public string $despatchGuideId,
        private string $tenantId,
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
