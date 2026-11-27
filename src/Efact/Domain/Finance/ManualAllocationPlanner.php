<?php

declare(strict_types=1);

namespace App\Efact\Domain\Finance;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/** Validates a split the user chose by hand against what each open item still owes. */
final readonly class ManualAllocationPlanner
{
    /**
     * The user's own split. Each line must name an item once and stay within what it still owes; the sum may not
     * exceed the amount received, and the difference is `unapplied`.
     *
     * @param list<OpenItem> $items
     * @param list<array{itemId: string, amount: BigDecimal}> $lines
     */
    public function plan(array $items, BigDecimal $amount, array $lines): AllocationResult
    {
        $received = $this->positive($amount);
        $open = [];
        foreach ($items as $item) {
            $open[$item->id] = Money::money($item->open);
        }

        $allocations = [];
        $applied = BigDecimal::zero();
        $seen = [];
        foreach ($lines as $line) {
            $id = $line['itemId'];
            if (isset($seen[$id])) {
                throw new InvalidArgumentException(sprintf('Item %s appears more than once.', $id));
            }
            $seen[$id] = true;
            if (!isset($open[$id])) {
                throw new InvalidArgumentException(sprintf('Item %s is not open.', $id));
            }

            $lineAmount = Money::money($line['amount']);
            if (!$lineAmount->isPositive()) {
                throw new InvalidArgumentException('Each allocated amount must be greater than zero.');
            }
            if ($lineAmount->isGreaterThan($open[$id])) {
                throw new InvalidArgumentException(sprintf('Item %s only owes %s.', $id, (string) $open[$id]));
            }

            $allocations[] = new Allocation($id, $lineAmount);
            $applied = $applied->plus($lineAmount);
        }

        if ($applied->isGreaterThan($received)) {
            throw new InvalidArgumentException('The allocated total exceeds the amount received.');
        }

        return new AllocationResult($allocations, $received->minus($applied));
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
