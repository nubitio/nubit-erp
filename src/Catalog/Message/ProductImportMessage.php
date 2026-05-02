<?php

declare(strict_types=1);

namespace App\Catalog\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class ProductImportMessage
{
    public function __construct(
        public string $jobId,
        public string $filePath,
        public string $tenantOrigin,
    ) {
    }
}
