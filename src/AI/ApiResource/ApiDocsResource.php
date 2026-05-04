<?php

declare(strict_types=1);

namespace App\AI\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpResource;
use App\AI\State\ApiDocsProvider;

#[ApiResource(
    shortName: 'ApiDocsResource',
    operations: [],
    paginationEnabled: false,
    mcp: [
        'resource://efact/api-docs' => new McpResource(
            uri: 'resource://efact/api-docs',
            name: 'Efact-API-Documentation',
            description: 'Full Hydra/JSON-LD API documentation for the Efact application. Read this first to discover available resources, operations, and their parameters.',
            mimeType: 'application/ld+json',
            provider: ApiDocsProvider::class,
            structuredContent: false,
        ),
    ],
)]
class ApiDocsResource
{
    public string $uri = '';

    public string $content = '';
}
