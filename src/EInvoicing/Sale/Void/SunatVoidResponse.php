<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale\Void;

use App\EInvoicing\Billing\Model\Response\SummaryResponse;

final readonly class SunatVoidResponse
{
    public function __construct(
        public SummaryResponse $rawResponse,
        public string $name,
        public string $ticket,
        public bool $success,
        public ?string $message,
    ) {
    }

    public function getCorrelation(): ?string
    {
        return explode('-', $this->name)[3] ?? null;
    }
}
