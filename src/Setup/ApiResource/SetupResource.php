<?php

declare(strict_types=1);

namespace App\Setup\ApiResource;

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
        // Public on purpose: it bootstraps an empty tenant (access_control lets /api/setup through) and
        // InitialSetupService refuses once a user exists. Said out loud so the security audit knows.
        new Get(
            output: SetupStatusDto::class,
            provider: SetupStatusProvider::class,
            security: "is_granted('PUBLIC_ACCESS')",
        ),
        new Post(
            security: "is_granted('PUBLIC_ACCESS')",
            input: InitialSetupInput::class,
            output: SetupStatusDto::class,
            read: false,
            processor: SetupProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
class SetupResource {}
