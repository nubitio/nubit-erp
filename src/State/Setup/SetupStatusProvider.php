<?php

namespace App\State\Setup;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Setup\SetupStatusDto;
use App\Setup\InitialSetupService;

readonly class SetupStatusProvider implements ProviderInterface
{
    public function __construct(private InitialSetupService $service)
    {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SetupStatusDto
    {
        return $this->service->status();
    }
}
