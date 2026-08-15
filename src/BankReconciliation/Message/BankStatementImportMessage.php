<?php

declare(strict_types=1);

namespace App\BankReconciliation\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class BankStatementImportMessage
{
    public function __construct(
        public string $importId,
        public string $filePath,
        public string $bankProfile,
        public string $tenantOrigin,
        public ?string $tenantName = null,
        public ?int $tenantId = null,
        public ?string $tenantDomain = null,
        public ?string $requestId = null,
    ) {}
}
