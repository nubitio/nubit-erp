<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Settings\Entity\SunatOperationalState;
use App\Settings\Service\SunatStateService;

/** @implements ProcessorInterface<SunatOperationalState, SunatOperationalState> */
final readonly class SunatStatePatchProcessor implements ProcessorInterface
{
    public function __construct(
        private SunatStateService $service,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SunatOperationalState
    {
        /** @var SunatOperationalState $data */
        $this->service->save($data);

        return $data;
    }
}
