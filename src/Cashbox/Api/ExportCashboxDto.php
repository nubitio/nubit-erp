<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    uriTemplate: '/cashboxes/{caja_id}/export',
    shortName: 'Cashbox',
    operations: [
        new Post(
            controller: ExportCashbox::class,
            openapiContext: [
                'summary' => 'Export cashbox report',
                'description' => 'Export cashbox report',
                'responses' => [
                    '200' => [
                        'description' => 'Cashbox report',
                        'content' => [
                            'application/pdf' => [
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        ),
    ]
)]
class ExportCashboxDto
{
    public string $caja_id;
}
