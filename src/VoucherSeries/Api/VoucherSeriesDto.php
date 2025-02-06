<?php

namespace App\VoucherSeries\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\VoucherSeries\Domain\VoucherSeries;

class VoucherSeriesDto extends VoucherSeries
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
