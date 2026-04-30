<?php

declare(strict_types=1);

namespace App\Billing\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class SendInvoiceMessage
{
    public function __construct(
        public string $taxDocumentId,
        private string $tenantId,
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
