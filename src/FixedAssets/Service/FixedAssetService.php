<?php

declare(strict_types=1);

namespace App\FixedAssets\Service;

use App\Efact\Domain\Money;
use App\FixedAssets\Entity\AssetDepreciationEntry;
use App\FixedAssets\Entity\FixedAsset;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Nubit\Platform\Exception\ValidationException;

/**
 * Guards FixedAsset status transitions and computes straight-line
 * depreciation entries.
 */
readonly class FixedAssetService
{
    /** Forward-only transition graph; a status can only move to the states listed. */
    private const array ALLOWED_TRANSITIONS = [
        FixedAsset::ACTIVE => [FixedAsset::FULLY_DEPRECIATED, FixedAsset::DISPOSED],
        FixedAsset::FULLY_DEPRECIATED => [FixedAsset::DISPOSED],
        FixedAsset::DISPOSED => [],
    ];

    public function transitionTo(FixedAsset $fixedAsset, string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$fixedAsset->getStatus()] ?? [];
        if (!in_array($newStatus, $allowed, strict: true)) {
            throw ValidationException::withErrors([
                'status' => [sprintf(
                    'No se puede pasar el activo de "%s" a "%s".',
                    $fixedAsset->getStatus(),
                    $newStatus,
                )],
            ]);
        }

        $fixedAsset->setStatus($newStatus);
    }

    /**
     * Generates one AssetDepreciationEntry per calendar month, from the
     * month after $lastGeneratedPeriod (or the asset's purchase month, if
     * none generated yet) through $throughPeriod inclusive. Each entry is
     * cost/usefulLifeMonths, except the final one, which is adjusted
     * ("plugged") so accumulated depreciation lands exactly on cost, never
     * over. Mutates $fixedAsset's accumulatedDepreciation and transitions
     * it to FULLY_DEPRECIATED once fully depreciated. Returns the new
     * entries — not persisted, the caller is responsible for that.
     *
     * @return list<AssetDepreciationEntry>
     */
    public function generateEntriesThrough(
        FixedAsset $fixedAsset,
        ?DateTimeImmutable $lastGeneratedPeriod,
        DateTimeImmutable $throughPeriod,
    ): array {
        if ($fixedAsset->getStatus() !== FixedAsset::ACTIVE) {
            return [];
        }

        $cost = Money::money($fixedAsset->getCost());
        $accumulated = Money::money($fixedAsset->getAccumulatedDepreciation());
        if ($accumulated->isGreaterThanOrEqualTo($cost)) {
            return [];
        }

        $monthly = Money::div($cost, BigDecimal::of($fixedAsset->getUsefulLifeMonths()), Money::SCALE);

        $period = $lastGeneratedPeriod instanceof DateTimeImmutable
            ? $this->firstOfMonth($lastGeneratedPeriod)->modify('+1 month')
            : $this->firstOfMonth($fixedAsset->getPurchaseDate());
        $through = $this->firstOfMonth($throughPeriod);

        $entries = [];
        while ($period <= $through && $accumulated->isLessThan($cost)) {
            $remaining = Money::sub($cost, $accumulated);
            $amount = $monthly->isGreaterThan($remaining) ? $remaining : $monthly;
            $accumulated = Money::add($accumulated, $amount);

            $entry = new AssetDepreciationEntry();
            $entry->setFixedAsset($fixedAsset);
            $entry->setPeriod($period);
            $entry->setAmount(Money::toString($amount));
            $entry->setAccumulatedAfter(Money::toString($accumulated));
            $entries[] = $entry;

            $period = $period->modify('+1 month');
        }

        $fixedAsset->setAccumulatedDepreciation(Money::toString($accumulated));
        if ($accumulated->isGreaterThanOrEqualTo($cost)) {
            $this->transitionTo($fixedAsset, FixedAsset::FULLY_DEPRECIATED);
        }

        return $entries;
    }

    private function firstOfMonth(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->modify('first day of this month')->setTime(0, 0);
    }
}
