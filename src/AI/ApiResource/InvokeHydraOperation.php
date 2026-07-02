<?php

declare(strict_types=1);

namespace App\AI\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use App\AI\State\InvokeHydraOperationProcessor;

#[ApiResource(shortName: 'InvokeHydraOperation', operations: [], paginationEnabled: false, mcp: [
    'invoke_hydra_operation' => new McpTool(
        name: 'invoke_hydra_operation',
        description: 'Execute a state-changing operation (POST, PATCH, DELETE) on an Efact API resource. Use the Hydra docs to discover valid URIs and payloads.',
        processor: InvokeHydraOperationProcessor::class,
        structuredContent: false,
    ),
])]
class InvokeHydraOperation
{
    public string $uri = '';

    public string $method = 'POST';

    /** @var array<string, mixed>|null */
    #[ApiProperty(schema: ['type' => 'object', 'description' => 'JSON payload for the request body (for POST/PATCH)'])]
    public ?array $payload = null;
}
