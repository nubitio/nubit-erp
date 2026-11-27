<?php

declare(strict_types=1);

namespace App\Efact\Domain\Finance;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Splits one amount over open items. Pure: no persistence, no side effects, so receivables and payables share
 * it without sharing an aggregate (ADR: shared primitives, separate models).
 *
 * Whatever the open items cannot absorb is returned as `unapplied` instead of being rejected: the caller
 * decides whether that becomes an advance or an error. Ties are broken by id so the result is reproducible.
 */
final readonly class AllocationPlanner
{
    /** @param list<OpenItem> $items */
    public function plan(
        array $items,
        BigDecimal $amount,
        AllocationStrategy $strategy = AllocationStrategy::OldestDue,
    ): AllocationResult {
        $remaining = $this->positive($amount);

        usort($items, static fn(OpenItem $a, OpenItem $b): int => match ($strategy) {
            AllocationStrategy::OldestDue => [$a->dueDate, $a->issuedOn, $a->id] <=> [
                $b->dueDate,
                $b->issuedOn,
                $b->id,
            ],
            AllocationStrategy::OldestIssued => [$a->issuedOn, $a->dueDate, $a->id] <=> [
                $b->issuedOn,
                $b->dueDate,
                $b->id,
            ],
        });

        $allocations = [];
        foreach ($items as $item) {
            if ($remaining->isZero()) {
                break;
            }
            $take = BigDecimal::min($remaining, Money::money($item->open));
            if (!$take->isPositive()) {
                continue;
            }
            $allocations[] = new Allocation($item->id, $take);
            $remaining = $remaining->minus($take);
        }

        return new AllocationResult($allocations, $remaining);
    }

    private function positive(BigDecimal $amount): BigDecimal
    {
        $money = Money::money($amount);
        if (!$money->isPositive()) {
            throw new InvalidArgumentException('The amount to allocate must be greater than zero.');
        }

        return $money;
    }
}
