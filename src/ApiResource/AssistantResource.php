<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\AI\Dto\AssistantRequest;
use App\AI\Dto\AssistantResponse;
use App\Controller\AssistantController;

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
