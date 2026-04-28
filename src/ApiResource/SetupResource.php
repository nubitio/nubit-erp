<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Setup\Dto\InitialSetupInput;
use App\Setup\Dto\SetupStatusDto;
use App\Setup\State\SetupProcessor;
use App\Setup\State\SetupStatusProvider;

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
