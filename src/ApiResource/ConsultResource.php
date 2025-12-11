<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Controller\ConsultController;

#[ApiResource(
    uriTemplate: '/consults/dni/{id}',
    shortName: 'Consult',
    description: 'Retrieves DNI info.',
    operations: [new Get(read: false)],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: ConsultResource::class,
        ),
    ],
    defaults: ['docType' => 'dni'],
    controller: ConsultController::class
)]
#[ApiResource(
    uriTemplate: '/consults/ruc/{id}',
    shortName: 'Consult',
    description: 'Retrieves RUC info.',
    operations: [new Get(read: false)],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: ConsultResource::class,
        ),
    ],
    defaults: ['docType' => 'ruc'],
    controller: ConsultController::class
)]
class ConsultResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $name,
        public string $address,
        public bool $active,
    ) {
    }
}
