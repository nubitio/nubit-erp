<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Controller\PrintESCPOSController;
use App\Core\Domain\Common\ApiResponse;
use App\Dto\Sale\VoidSaleDto;
use App\Entity\Sale;
use App\State\Sale\MakeInvoiceProcessor;
use App\State\Sale\VoidInvoiceProcessor;

#[ApiResource(
    shortName: 'Sale',
    operations: [
        new Post(
            uriTemplate: '/sales/{id}/invoice',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'movimiento_id',
                    fromClass: Sale::class
                ),
            ],
            processor: MakeInvoiceProcessor::class,
        ),
        new Post(
            uriTemplate: '/sales/{id}/void',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'movimiento_id',
                    fromClass: Sale::class
                ),
            ],
            input: VoidSaleDto::class,
            output: ApiResponse::class,
            processor: VoidInvoiceProcessor::class,
        ),
        new Get(
            uriTemplate: '/sales/{id}/print-invoice',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'movimiento_id',
                    fromClass: Sale::class
                ),
            ],
            controller: PrintESCPOSController::class,
            openapiContext: [
                'summary' => 'Print invoice',
                'description' => 'Print invoice in ESC/POS format',
                'responses' => [
                    '200' => [
                        'description' => 'Invoice printed',
                        'content' => [
                            'application/octet-stream' => [
                                'schema' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            read: false,
        ),
    ]
)]
class SaleResource
{
}
