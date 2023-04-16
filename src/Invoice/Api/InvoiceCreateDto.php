<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new Post()
    ],
    provider: InvoiceProvider::class,
    processor: InvoiceProcessor::class
)]
class InvoiceCreateDto
{
    #[ApiProperty(identifier: true)]
    public string $sale_id;
}