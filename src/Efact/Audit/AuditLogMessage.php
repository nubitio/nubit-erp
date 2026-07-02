<?php

declare(strict_types=1);

namespace App\Efact\Audit;

final readonly class AuditLogMessage
{
    public function __construct(
        public string $action,
        public string $metadata,
        public ?int $tenantId,
        public string $actor,
        public ?string $channel,
        public ?string $requestId,
    ) {}
}
