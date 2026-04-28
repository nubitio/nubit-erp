<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use App\AI\State\ReadHydraResourceProcessor;

#[ApiResource(
    shortName: 'ReadHydraResource',
    operations: [],
    paginationEnabled: false,
    mcp: [
        'read_hydra_resource' => new McpTool(
            name: 'read_hydra_resource',
            description: 'Navigate to a Hydra API resource or collection by URI. Use this to GET any resource from the Efact API.',
            processor: ReadHydraResourceProcessor::class,
            structuredContent: false,
        ),
    ],
)]
class ReadHydraResource
{
    public string $uri = '';
}
