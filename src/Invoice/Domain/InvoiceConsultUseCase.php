<?php

namespace App\Invoice\Domain;

class InvoiceConsultUseCase
{
    /**
     * @return array<string, true>
     */
    public function __invoke(string $saleId): array
    {
        return [
            'success' => true,
        ];
    }
}
