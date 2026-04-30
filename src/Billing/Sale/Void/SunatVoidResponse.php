<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Billing\Sunat\Model\Response\SummaryResponse;

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
