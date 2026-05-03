<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Settings\Entity\TenantSettings;
use App\Settings\Service\TenantSettingsService;

/** @implements ProcessorInterface<TenantSettings, TenantSettings> */
final readonly class TenantSettingsPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private TenantSettingsService $service,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TenantSettings
    {
        /** @var TenantSettings $data */
        $this->service->save($data);

        return $data;
    }
}
