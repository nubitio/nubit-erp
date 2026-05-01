<?php

declare(strict_types=1);

namespace App\AI\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\AI\Controller\AssistantController;
use App\AI\Dto\AssistantRequest;
use App\AI\Dto\AssistantResponse;

#[ApiResource(
    uriTemplate: '/assistant',
    shortName: 'Assistant',
    operations: [
        new Post(
            input: AssistantRequest::class,
            output: AssistantResponse::class,
            read: false,
            controller: AssistantController::class,
        ),
    ],
    paginationEnabled: false,
)]
class AssistantResource
{
}
