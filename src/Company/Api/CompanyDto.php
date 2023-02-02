<?php

namespace App\Company\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Company\Domain\Company;

#[ApiResource(
    shortName: 'Company',
    provider: CompanyProvider::class,
    processor: CompanyProcessor::class,
)]
class CompanyDto extends Company
{
    #[ApiProperty(identifier: true)]
    public ?string $empresa_id;
}