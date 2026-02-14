<?php

declare(strict_types=1);

namespace App\Core\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class TenantBackupMessage
{
    public function __construct(
        public int $tenantId,
        public string $tenantName,
    ) {
    }
}

