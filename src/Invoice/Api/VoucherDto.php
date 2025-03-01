<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Invoice\Domain\Voucher;

#[ApiResource(
    shortName: 'Invoice',
    operations: [
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
}
