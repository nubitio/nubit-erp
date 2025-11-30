<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Controller\SaleReportController;
use ArrayObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[ApiResource(
    shortName: 'SaleReport',
    operations: [
        new Get(
            uriTemplate: '/sales/report',
            controller: SaleReportController::class,
            openapi: new Operation(
                responses: [
                    '200' => new Response(
                        description: 'Report generated successfully',
                        content: new ArrayObject([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'binary'
                                ]
                            ]
                        ])
                    ),
                    '400' => new Response(description: 'Invalid input'),
                    '404' => new Response(description: 'Report not found'),
                ],
                summary: 'Generate sales report',
                description: 'Generates a sales report based on the specified parameters',
                parameters: [
                    [
                        'name' => 'report',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                            'enum' => [
                                'sale-by-product',
                                'detailed-sales',
                                'accounting-report',
                                'airport-report',
                                'voided-sales',
                                'credit-notes',
                                'sale-notes'
                            ]
                        ],
                        'description' => 'Type of report to generate'
                    ],
                    [
                        'name' => 'date-from',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'string', 'format' => 'date'],
                        'description' => 'Start date for the report (YYYY-MM-DD)'
                    ],
                    [
                        'name' => 'date-to',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'string', 'format' => 'date'],
                        'description' => 'End date for the report (YYYY-MM-DD)'
                    ]
                ]
            ),
            output: StreamedResponse::class,
            read: false
        )
    ]
)]
class SaleReportResource
{
}
