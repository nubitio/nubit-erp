<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

readonly class InvoiceSendProcessResult
{
    public function __construct(
        public bool $sent,
        public ?string $error,
        public bool $paused,
        public ?string $skipReason,
    ) {}
}
