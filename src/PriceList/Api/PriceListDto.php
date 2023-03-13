<?php

namespace App\PriceList\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\PriceList\Domain\PriceList;

#[ApiResource(
    shortName: 'PriceList',
    provider: PriceListProvider::class,
    processor: PriceListProcessor::class,
)]
class PriceListDto extends PriceList
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}