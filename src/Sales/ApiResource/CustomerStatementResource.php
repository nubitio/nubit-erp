<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Sales\Controller\CustomerStatementController;
use ArrayObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[ApiResource(shortName: 'CustomerStatement', operations: [
    new Get(
        uriTemplate: '/sales/reports/customer-statement',
        controller: CustomerStatementController::class,
        openapi: new Operation(
            responses: [
                '200' => new Response(description: 'Statement generated', content: new ArrayObject([
                    'application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                        'schema' => ['type' => 'string', 'format' => 'binary'],
                    ],
                ])),
                '400' => new Response(description: 'Invalid input'),
                '404' => new Response(description: 'Customer not found'),
            ],
            summary: 'Customer account statement with aging',
            description: 'Chronological charges and abonos with a running balance, period totals and an aging '
            . 'breakdown, for one customer. Built from credit sales and sale_payments.',
            parameters: [
                [
                    'name' => 'customer',
                    'in' => 'query',
                    'required' => true,
                    'schema' => ['type' => 'integer'],
                    'description' => 'Customer id',
                ],
                [
                    'name' => 'date-from',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string', 'format' => 'date'],
                    'description' => 'Range start (YYYY-MM-DD); defaults to 90 days before date-to',
                ],
                [
                    'name' => 'date-to',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string', 'format' => 'date'],
                    'description' => 'Range end / cut-off (YYYY-MM-DD); defaults to today',
                ],
                [
                    'name' => 'format',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['pdf', 'xlsx'], 'default' => 'pdf'],
                    'description' => 'pdf to send to the customer, xlsx to work with',
                ],
            ],
        ),
        output: StreamedResponse::class,
        read: false,
    ),
])]
final class CustomerStatementResource {}
