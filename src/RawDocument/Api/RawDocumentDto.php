<?php

namespace App\RawDocument\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\RawDocument\Domain\RawDocument;

#[ApiResource(
    shortName: 'RawDocument',
    provider: RawDocumentProvider::class,
    processor: RawDocumentProcessor::class,
)]
class RawDocumentDto extends RawDocument
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}
