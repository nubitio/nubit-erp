<?php

declare(strict_types=1);

namespace App\EInvoicing\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\EInvoicing\Controller\ConsultController;

#[ApiResource(
    shortName: 'Consult',
    operations: [
        new Get(
            uriTemplate: '/consults/dni/{id}',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'id',
                    fromClass: ConsultResource::class,
                ),
            ],
            defaults: ['docType' => 'dni'],
            controller: ConsultController::class,
            description: 'Retrieves DNI info.',
            read: false,
        ),
        new Get(
            uriTemplate: '/consults/ruc/{id}',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'id',
                    fromClass: ConsultResource::class,
                ),
            ],
            defaults: ['docType' => 'ruc'],
            controller: ConsultController::class,
            description: 'Retrieves RUC info.',
            read: false,
        ),
    ],
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
