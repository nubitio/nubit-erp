<?php

declare(strict_types=1);

namespace App\Billing\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

/** Renders and caches the voucher PDF in the background right after issuance. */
#[AsMessage(transport: 'async')]
final readonly class PrewarmVoucherPdfMessage
{
    public function __construct(
        public string $taxDocumentId,
    ) {}
}
