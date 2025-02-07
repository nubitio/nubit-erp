<?php

namespace App\PriceList\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\PriceList\Domain\PriceList;

class PriceListDto extends PriceList
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
