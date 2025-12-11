<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\Setup\InitialSetupInput;
use App\Dto\Setup\SetupStatusDto;
use App\State\Setup\SetupProcessor;
use App\State\Setup\SetupStatusProvider;

#[ApiResource(
    uriTemplate: '/setup',
    shortName: 'Setup',
    operations: [
        new Get(
            output: SetupStatusDto::class,
            provider: SetupStatusProvider::class
        ),
        new Post(
            input: InitialSetupInput::class,
            output: SetupStatusDto::class,
            read: false,
            processor: SetupProcessor::class
        ),
    ],
    paginationEnabled: false,
)]
class SetupResource
{
}
