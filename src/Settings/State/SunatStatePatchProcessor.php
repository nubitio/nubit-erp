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

        // API Platform deserializes PATCH into a NEW (detached) object when the
        // URI has no {id} variable. Calling persist() on it would INSERT a second
        // row instead of updating the singleton. Always fetch the managed entity
        // and copy the writable fields onto it so Doctrine issues an UPDATE.
        $state = $this->service->get();
        $wasEnabled = $state->isSendEnabled();

        $state->setSendEnabled($data->isSendEnabled());
        $state->setReceiptRequired($data->isReceiptRequired());
        $state->setRejectionThreshold($data->getRejectionThreshold());

        // When the admin re-enables sending, reset the streak so auto-pause
        // doesn't trigger again immediately.
        if ($state->isSendEnabled() && !$wasEnabled) {
            $state->resetStreak();
        }

        $this->service->save($state);

        return $state;
    }
}
