<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Billing\Invoice\Domain\Voucher;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
        new Get(
            uriTemplate: '/tax-documents/{tax_document_id}/voucher',
            uriVariables: [
                'tax_document_id' => 'tax_document_id',
            ],
            output: Voucher::class
        ),
        new Get(
            uriTemplate: '/invoices/{sale_id}/voucher',
            uriVariables: [
                'sale_id' => 'sale_id',
            ],
            output: Voucher::class
        ),
    ],
    provider: VoucherProvider::class,
)]
class VoucherDto extends Voucher
{
    #[ApiProperty(identifier: true)]
    public ?string $sale_id = null;

    public ?string $tax_document_id = null;
}
