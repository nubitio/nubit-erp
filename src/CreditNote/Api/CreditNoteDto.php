<?php

namespace App\CreditNote\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\CreditNote\Domain\CreditNote;

#[ApiResource(
    shortName: 'CreditNote',
    provider: CreditNoteProvider::class,
    processor: CreditNoteProcessor::class,
)]
class CreditNoteDto extends CreditNote
{
    #[ApiProperty(identifier: true)]
    public ?string $nota_id;
}
