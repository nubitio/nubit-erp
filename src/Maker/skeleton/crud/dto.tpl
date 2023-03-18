<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\{{module}}\Domain\{{module}};

#[ApiResource(
    shortName: '{{module}}',
    provider: {{module}}Provider::class,
    processor: {{module}}Processor::class,
)]
class {{module}}Dto extends {{module}}
{
    #[ApiProperty(identifier: true)]
{{entityIdField}}
}