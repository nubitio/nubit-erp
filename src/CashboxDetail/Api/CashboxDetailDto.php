<?php

namespace App\CashboxDetail\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\CashboxDetail\Domain\CashboxDetail;

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
