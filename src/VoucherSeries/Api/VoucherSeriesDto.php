<?php

namespace App\VoucherSeries\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\VoucherSeries\Domain\VoucherSeries;

#[ApiResource(
    shortName: 'VoucherSeries',
    provider: VoucherSeriesProvider::class,
    processor: VoucherSeriesProcessor::class,
)]
class VoucherSeriesDto extends VoucherSeries
{
    #[ApiProperty(identifier: true)]
    public ?int $id;

}