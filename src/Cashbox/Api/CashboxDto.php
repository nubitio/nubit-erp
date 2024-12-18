<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Cashbox\Domain\Cashbox;

#[ApiResource(
    shortName: 'Cashbox',
    provider: CashboxProvider::class,
    processor: CashboxProcessor::class,
)]
class CashboxDto extends Cashbox
{
    #[ApiProperty(identifier: true)]
    public ?string $caja_id;
}
