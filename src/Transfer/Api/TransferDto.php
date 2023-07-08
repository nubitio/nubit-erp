<?php

namespace App\Transfer\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Transfer\Domain\Transfer;

#[ApiResource(
    shortName: 'Transfer',
    provider: TransferProvider::class,
    processor: TransferProcessor::class,
)]
class TransferDto extends Transfer
{
    #[ApiProperty(identifier: true)]
    public ?string $transfer_id;

}