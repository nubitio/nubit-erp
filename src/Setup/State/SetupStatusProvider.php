<?php

declare(strict_types=1);

namespace App\Setup\State;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Setup\SetupStatusDto;
use App\Setup\InitialSetupService;

/**
 * @implements ProviderInterface<object>
 */
readonly class SetupStatusProvider implements ProviderInterface
{
    public function __construct(private InitialSetupService $service)
    {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SetupStatusDto
    {
        return $this->service->status();
    }
}
