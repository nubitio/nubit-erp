<?php

declare(strict_types=1);

namespace App\Inventory\Service;

/**
 * Replays kardex movements chronologically and values each line using weighted average cost.
 *
 * Each inbound movement recalculates the average; outbound movements consume at the current average.
 */
final class WeightedAverageKardexValuator
{
    private const int QTY_SCALE = 6;
    private const int MONEY_SCALE = 2;
    private const int COST_SCALE = 4;
    private const string EPSILON = '0.000001';

    /**
     * @param list<array{
     *     warehouse_id: string,
     *     qty_delta: numeric-string,
     *     inbound_unit_cost: numeric-string|null
     * }> $movements chronologically ordered (ASC)
     *
     * @return list<array{unit_cost: string, line_value: string, running_value: string}>
     */
    public function apply(array $movements): array
    {
        /** @var array<string, numeric-string> $qtyByWarehouse */
        $qtyByWarehouse = [];
        /** @var array<string, numeric-string> $avgCostByWarehouse */
        $avgCostByWarehouse = [];
        $results = [];

        foreach ($movements as $movement) {
            $warehouseId = $movement['warehouse_id'];
            $delta = $movement['qty_delta'];
            $qty = $qtyByWarehouse[$warehouseId] ?? '0.000000';
            $avgCost = $avgCostByWarehouse[$warehouseId] ?? '0.0000';

            if (bccomp($delta, '0', self::QTY_SCALE) > 0) {
                $inboundCost = $this->formatCost($movement['inbound_unit_cost'] ?? '0');
                $oldValue = $this->multiplyMoneyRaw($qty, $avgCost);
                $inValue = $this->multiplyMoneyRaw($delta, $inboundCost);
                $newQty = bcadd($qty, $delta, self::QTY_SCALE);
                $newAvg = bccomp($newQty, self::EPSILON, self::QTY_SCALE) > 0
                    ? $this->formatCost(bcdiv(bcadd($oldValue, $inValue, 8), $newQty, self::COST_SCALE))
                    : $inboundCost;

                $displayUnitCost = $inboundCost;
                $lineValue = $this->formatMoney($inValue);
                $qtyByWarehouse[$warehouseId] = $newQty;
                $avgCostByWarehouse[$warehouseId] = $newAvg;
            } else {
                $outQty = bcsub('0', $delta, self::QTY_SCALE);
                $displayUnitCost = $avgCost;
                $lineValue = $this->formatMoney($this->multiplyMoneyRaw($outQty, $avgCost));
                $qtyByWarehouse[$warehouseId] = bcadd($qty, $delta, self::QTY_SCALE);
            }

            $runningQty = $qtyByWarehouse[$warehouseId];
            $runningAvg = $avgCostByWarehouse[$warehouseId] ?? '0.0000';

            $results[] = [
                'unit_cost' => $displayUnitCost,
                'line_value' => $lineValue,
                'running_value' => $this->formatMoney($this->multiplyMoneyRaw($runningQty, $runningAvg)),
            ];
        }

        return $results;
    }

    /**
     * @param list<array{
     *     warehouse_id: string,
     *     qty_delta: numeric-string,
     *     inbound_unit_cost: numeric-string|null
     * }> $movements chronologically ordered (ASC)
     *
     * @return array{stockValue: string, avgUnitCost: string|null}
     */
    public function endingMetrics(array $movements): array
    {
        /** @var array<string, numeric-string> $qtyByWarehouse */
        $qtyByWarehouse = [];
        /** @var array<string, numeric-string> $avgCostByWarehouse */
        $avgCostByWarehouse = [];

        foreach ($movements as $movement) {
            $warehouseId = $movement['warehouse_id'];
            $delta = $movement['qty_delta'];
            $qty = $qtyByWarehouse[$warehouseId] ?? '0.000000';
            $avgCost = $avgCostByWarehouse[$warehouseId] ?? '0.0000';

            if (bccomp($delta, '0', self::QTY_SCALE) > 0) {
                $inboundCost = $this->formatCost($movement['inbound_unit_cost'] ?? '0');
                $oldValue = $this->multiplyMoneyRaw($qty, $avgCost);
                $inValue = $this->multiplyMoneyRaw($delta, $inboundCost);
                $newQty = bcadd($qty, $delta, self::QTY_SCALE);
                $newAvg = bccomp($newQty, self::EPSILON, self::QTY_SCALE) > 0
                    ? $this->formatCost(bcdiv(bcadd($oldValue, $inValue, 8), $newQty, self::COST_SCALE))
                    : $inboundCost;

                $qtyByWarehouse[$warehouseId] = $newQty;
                $avgCostByWarehouse[$warehouseId] = $newAvg;
            } else {
                $qtyByWarehouse[$warehouseId] = bcadd($qty, $delta, self::QTY_SCALE);
            }
        }

        $totalQty = '0';
        $totalValue = '0';

        foreach ($qtyByWarehouse as $warehouseId => $qty) {
            $avg = $avgCostByWarehouse[$warehouseId] ?? '0.0000';
            $totalQty = bcadd($totalQty, $qty, self::QTY_SCALE);
            $totalValue = bcadd($totalValue, $this->multiplyMoneyRaw($qty, $avg), 8);
        }

        $stockValue = $this->formatMoney($totalValue);
        $avgUnitCost = bccomp($totalQty, self::EPSILON, self::QTY_SCALE) > 0
            ? $this->formatCost(bcdiv($totalValue, $totalQty, self::COST_SCALE))
            : null;

        return [
            'stockValue' => $stockValue,
            'avgUnitCost' => $avgUnitCost,
        ];
    }

    /**
     * @param numeric-string $qty
     * @param numeric-string $unitCost
     * @return numeric-string
     */
    private function multiplyMoneyRaw(string $qty, string $unitCost): string
    {
        return bcmul($qty, $unitCost, 8);
    }

    /** @return numeric-string */
    private function formatMoney(string $value): string
    {
        /** @var numeric-string $formatted */
        $formatted = number_format((float) $value, self::MONEY_SCALE, '.', '');

        return $formatted;
    }

    /** @return numeric-string */
    private function formatCost(string $value): string
    {
        /** @var numeric-string $formatted */
        $formatted = number_format((float) $value, self::COST_SCALE, '.', '');

        return $formatted;
    }
}
