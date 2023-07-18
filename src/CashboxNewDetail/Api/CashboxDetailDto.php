<?php

namespace App\CashboxNewDetail\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\CashboxNewDetail\Domain\CashboxDetail;

#[ApiResource(
    shortName: 'CashboxDetail',
    provider: CashboxDetailProvider::class,
    processor: CashboxDetailProcessor::class,
)]
class CashboxDetailDto extends CashboxDetail
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
