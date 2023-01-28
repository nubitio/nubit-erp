<?php

namespace App\Consult\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Consult\Domain\Consult;

#[ApiResource(
    uriTemplate: '/consults/dni/{dni}',
    shortName: 'Consult',
    description: 'Retrieves DNI info.',
    operations: [new Get()],
    uriVariables: [
        'dni' => new Link(
            fromProperty: 'dni',
            fromClass: ConsultDto::class,
        ),
    ],
    provider: ConsultProvider::class
)]
#[ApiResource(
    uriTemplate: '/consults/ruc/{ruc}',
    shortName: 'Consult',
    description: 'Retrieves RUC info.',
    operations: [new Get()],
    uriVariables: [
        'ruc' => new Link(
            fromProperty: 'ruc',
            fromClass: ConsultDto::class,
        ),
    ],
    provider: ConsultProvider::class
)]
class ConsultDto extends Consult
{
    #[ApiProperty(identifier: true)]
    public string $id;
}