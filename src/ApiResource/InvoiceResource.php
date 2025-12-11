<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Sale;
use App\State\Invoice\ConsultInvoiceProvider;
use App\State\Invoice\SendEmailProcessor;
use App\State\Invoice\SendInvoiceProcessor;
use App\State\Invoice\ValidateInvoiceProcessor;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new Get(
            uriTemplate: '/invoices/{id}/consult',
            provider: ConsultInvoiceProvider::class
        ),
        new Post(
            uriTemplate: '/invoices/{id}/validate',
            processor: ValidateInvoiceProcessor::class
        ),
        new Post(
            uriTemplate: '/invoices/{id}/send',
            processor: SendInvoiceProcessor::class
        ),
        new Post(
            uriTemplate: '/invoices/{id}/email',
            processor: SendEmailProcessor::class
        ),
    ],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: Sale::class
        ),
    ]
)]
class InvoiceResource
{
}
