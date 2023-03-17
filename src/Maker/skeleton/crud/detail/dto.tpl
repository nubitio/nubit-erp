<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\{{masterModule}}\Api\{{masterModule}}Dto;
use App\{{module}}\Domain\{{module}};

#[ApiResource(
    uriTemplate: '/{{kebabModule}}/{{{entityId}}}/details',
    shortName: '{{module}}',
    operations: [new GetCollection()],
    uriVariables: [
        '{{entityId}}' => new Link(
            fromProperty: '{{entityId}}',
            fromClass: {{masterModule}}Dto::class,
        ),
    ],
    provider: {{module}}Provider::class
)]
class {{module}}Dto extends {{module}}
{
}