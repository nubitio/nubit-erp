<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Controller\Api\MakeInvoice;
use App\Controller\Api\VoidSale;
use App\Controller\PrintESCPOSController;
use App\Core\Exception\ServiceException;
use App\Core\Http\ApiResponse;
use App\Dto\Sale\VoidSaleDto;
use App\Entity\Sale;
use ArrayObject;

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
            controller: MakeInvoice::class,
        ),
        new Post(
            uriTemplate: '/sales/{id}/void',
            uriVariables: [
                'id' => new Link(
                    fromProperty: 'movimiento_id',
                    fromClass: Sale::class
                ),
            ],
            controller: VoidSale::class,
            exceptionToStatus: [
                ServiceException::class => 400,
            ],
            input: VoidSaleDto::class,
            output: ApiResponse::class
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
            openapi: new Operation(
                responses: [
                    '200' => new Response(
                        description: 'Invoice printed',
                        content: new ArrayObject([
                            'application/octet-stream' => [
                                'schema' => [
                                    'type' => 'string',
                                ],
                            ],
                        ])
                    ),
                ],
                summary: 'Print invoice',
                description: 'Print invoice in ESC/POS format',
            ),
            read: false,
        ),
    ]
)]
class SaleResource
{
}
