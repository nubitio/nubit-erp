<?php

namespace App\Branch\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Branch\Domain\Branch;

#[ApiResource(
    shortName: 'Branch',
    provider: BranchProvider::class,
    processor: BranchProcessor::class,
)]
class BranchDto extends Branch
{
    #[ApiProperty(identifier: true)]
    public ?string $local_id;
}