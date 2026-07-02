<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Settings\Entity\TenantSettings;
use App\Settings\Repository\TenantSettingsRepository;

/** @implements ProviderInterface<TenantSettings> */
final readonly class TenantSettingsProvider implements ProviderInterface
{
    public function __construct(
        private TenantSettingsRepository $repository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TenantSettings
    {
        return $this->repository->getOrCreate();
    }
}
