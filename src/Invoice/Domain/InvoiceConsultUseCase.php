<?php

namespace App\Invoice\Domain;

class InvoiceConsultUseCase
{
    public function __invoke(string $saleId): array
    {
        return [
            'success' => true,
        ];
    }
}
