<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Invoice\Domain\Invoice;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new GetCollection(
            output: Invoice::class,
        ),
    ],
    provider: InvoiceProvider::class,
    processor: InvoiceProcessor::class
)]
class InvoiceDto extends Invoice
{
    #[ApiProperty(identifier: true)]
    public ?string $comprobante_id;
}
