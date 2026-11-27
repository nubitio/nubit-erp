<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Lays the tenders end to end over the allocations, then over the excess. Pure and deterministic: the first
 * tender pays the first allocation until it runs out, and so on. Every cent of every tender lands in exactly
 * one slice.
 */
final readonly class CollectionSlicer
{
    /**
     * @param list<BigDecimal> $tenders amounts, in the order they were given
     * @param list<BigDecimal> $allocations amounts settled per allocation, in order
     *
     * @return list<CollectionSlice>
     */
    public function slice(array $tenders, array $allocations): array
    {
        $tenderTotal = $this->sum($tenders);
        $allocated = $this->sum($allocations);
        if ($allocated->isGreaterThan($tenderTotal)) {
            throw new InvalidArgumentException('The allocations exceed what the tenders add up to.');
        }

        // Targets: each allocation, then the excess (null) when something is left.
        $targets = [];
        foreach ($allocations as $index => $amount) {
            $targets[] = ['allocation' => $index, 'left' => Money::money($amount)];
        }
        $excess = $tenderTotal->minus($allocated);
        if ($excess->isPositive()) {
            $targets[] = ['allocation' => null, 'left' => $excess];
        }

        $slices = [];
        $cursor = 0;
        foreach ($tenders as $tenderIndex => $tender) {
            $capacity = Money::money($tender);
            while ($capacity->isPositive() && array_key_exists($cursor, $targets)) {
                $take = BigDecimal::min($capacity, $targets[$cursor]['left']);
                $slices[] = new CollectionSlice($tenderIndex, $targets[$cursor]['allocation'], $take);
                $capacity = $capacity->minus($take);
                $targets[$cursor]['left'] = $targets[$cursor]['left']->minus($take);
                if ($targets[$cursor]['left']->isZero()) {
                    ++$cursor;
                }
            }
        }

        return $slices;
    }

    /** @param list<BigDecimal> $amounts */
    private function sum(array $amounts): BigDecimal
    {
        return array_reduce(
            $amounts,
            static fn(BigDecimal $carry, BigDecimal $amount): BigDecimal => $carry->plus(Money::money($amount)),
            BigDecimal::zero(),
        );
    }
}
