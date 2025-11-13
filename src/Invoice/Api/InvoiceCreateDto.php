<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\MakeInvoice;
use App\Invoice\Domain\Voucher;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new Post(
            input: InvoiceCreateDto::class,
            output: Voucher::class,
        ),
    ],
    processor: MakeInvoice::class
)]
class InvoiceCreateDto
{
    #[ApiProperty(identifier: true)]
    public string $sale_id;
}
