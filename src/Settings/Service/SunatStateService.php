<?php

declare(strict_types=1);

namespace App\Settings\Service;

use App\Settings\Entity\SunatOperationalState;
use App\Settings\Repository\SunatOperationalStateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Typed access layer for SUNAT operational state.
 *
 * Replaces all SettingsService::get(Constants::SUNAT_*) calls.
 * The system writes streak/sendEnabled here automatically;
 * admins can toggle sendEnabled and receiptRequired via the API.
 */
readonly class SunatStateService
{
    public function __construct(
        private SunatOperationalStateRepository $repository,
        private EntityManagerInterface $em,
    ) {
    }

    public function get(): SunatOperationalState
    {
        return $this->repository->getOrCreate();
    }

    public function isSendEnabled(): bool
    {
        return $this->get()->isSendEnabled();
    }

    public function isReceiptRequired(): bool
    {
        return $this->get()->isReceiptRequired();
    }

    public function getRejectionThreshold(): int
    {
        return $this->get()->getRejectionThreshold();
    }

    /**
     * Increments the rejection streak and returns the new value.
     * Caller is responsible for checking isStreakAtThreshold() afterwards.
     */
    public function incrementRejectionStreak(): int
    {
        $state = $this->get();
        $newStreak = $state->incrementStreak();
        $this->em->flush();

        return $newStreak;
    }

    public function resetRejectionStreak(): void
    {
        $state = $this->get();
        if ($state->getRejectionStreak() === 0) {
            return;
        }
        $state->resetStreak();
        $this->em->flush();
    }

    public function pauseSending(): void
    {
        $state = $this->get();
        $state->setSendEnabled(false);
        $this->em->flush();
    }

    public function save(SunatOperationalState $state): void
    {
        $this->em->persist($state);
        $this->em->flush();
    }
}
