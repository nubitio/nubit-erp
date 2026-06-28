<?php

declare(strict_types=1);

namespace App\Inventory\Service;

/**
 * Replays kardex movements chronologically and values each line using FIFO layers.
 *
 * Each inbound movement adds a layer; outbound movements consume oldest layers first.
 * Quantity arithmetic uses bcmath (scale 6) to avoid float drift.
 */
final class FifoKardexValuator
{
    private const int QTY_SCALE = 6;
    private const int MONEY_SCALE = 2;
    private const int COST_SCALE = 4;
    private const string EPSILON = '0.000001';

    /**
     * @param list<array{
     *     warehouse_id: string,
     *     qty_delta: string,
     *     inbound_unit_cost: string|null
     * }> $movements chronologically ordered (ASC)
     *
     * @return list<array{unit_cost: string, line_value: string, running_value: string}>
     */
    public function apply(array $movements): array
    {
        /** @var array<string, list<array{qty: string, cost: string}>> $layersByWarehouse */
        $layersByWarehouse = [];
        $results = [];

        foreach ($movements as $movement) {
            $warehouseId = $movement['warehouse_id'];
            $layers = $layersByWarehouse[$warehouseId] ?? [];
            $delta = $movement['qty_delta'];

            if (bccomp($delta, '0', self::QTY_SCALE) > 0) {
                $unitCost = $this->formatCost($movement['inbound_unit_cost'] ?? '0');
                $layers[] = ['qty' => $delta, 'cost' => $unitCost];
                $lineValue = $this->formatMoney($this->multiplyMoneyRaw($delta, $unitCost));
                $displayUnitCost = $unitCost;
            } else {
                $qtyToConsume = bcsub('0', $delta, self::QTY_SCALE);
                [$layers, $consumedValue, $consumedQty] = $this->consumeFifo($layers, $qtyToConsume);
                $displayUnitCost = bccomp($consumedQty, self::EPSILON, self::QTY_SCALE) > 0
                    ? $this->formatCost(bcdiv($consumedValue, $consumedQty, self::COST_SCALE))
                    : '0.0000';
                $lineValue = $this->formatMoney($consumedValue);
            }

            $layersByWarehouse[$warehouseId] = $layers;

            $results[] = [
                'unit_cost' => $displayUnitCost,
                'line_value' => $lineValue,
                'running_value' => $this->formatMoney($this->layersTotalValue($layers)),
            ];
        }

        return $results;
    }

    /**
     * @param list<array{
     *     warehouse_id: string,
     *     qty_delta: string,
     *     inbound_unit_cost: string|null
     * }> $movements chronologically ordered (ASC)
     *
     * @return array{stockValue: string, avgUnitCost: string|null}
     */
    public function endingMetrics(array $movements): array
    {
        /** @var array<string, list<array{qty: string, cost: string}>> $layersByWarehouse */
        $layersByWarehouse = [];

        foreach ($movements as $movement) {
            $warehouseId = $movement['warehouse_id'];
            $layers = $layersByWarehouse[$warehouseId] ?? [];
            $delta = $movement['qty_delta'];

            if (bccomp($delta, '0', self::QTY_SCALE) > 0) {
                $layers[] = [
                    'qty' => $delta,
                    'cost' => $this->formatCost($movement['inbound_unit_cost'] ?? '0'),
                ];
            } else {
                $qtyToConsume = bcsub('0', $delta, self::QTY_SCALE);
                [$layers] = $this->consumeFifo($layers, $qtyToConsume);
            }

            $layersByWarehouse[$warehouseId] = $layers;
        }

        $totalQty = '0';
        $totalValue = '0';

        foreach ($layersByWarehouse as $layers) {
            foreach ($layers as $layer) {
                $totalQty = bcadd($totalQty, $layer['qty'], self::QTY_SCALE);
                $totalValue = bcadd($totalValue, $this->multiplyMoneyRaw($layer['qty'], $layer['cost']), 8);
            }
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
     * @param list<array{qty: string, cost: string}> $layers
     *
     * @return array{0: list<array{qty: string, cost: string}>, 1: string, 2: string}
     */
    private function consumeFifo(array $layers, string $qtyToConsume): array
    {
        $remaining = $qtyToConsume;
        $consumedValue = '0';
        $consumedQty = '0';
        $newLayers = [];

        foreach ($layers as $layer) {
            if (bccomp($remaining, self::EPSILON, self::QTY_SCALE) <= 0) {
                $newLayers[] = $layer;
                continue;
            }

            $available = $layer['qty'];

            if (bccomp($available, $remaining, self::QTY_SCALE) <= 0) {
                $take = $available;
                $remaining = bcsub($remaining, $take, self::QTY_SCALE);
            } else {
                $take = $remaining;
                $remaining = '0';
                $newLayers[] = [
                    'qty' => bcsub($available, $take, self::QTY_SCALE),
                    'cost' => $layer['cost'],
                ];
            }

            $consumedQty = bcadd($consumedQty, $take, self::QTY_SCALE);
            $consumedValue = bcadd($consumedValue, $this->multiplyMoneyRaw($take, $layer['cost']), 8);
        }

        return [$newLayers, $consumedValue, $consumedQty];
    }

    /**
     * @param list<array{qty: string, cost: string}> $layers
     */
    private function layersTotalValue(array $layers): string
    {
        $total = '0';

        foreach ($layers as $layer) {
            $total = bcadd($total, $this->multiplyMoneyRaw($layer['qty'], $layer['cost']), 8);
        }

        return $total;
    }

    private function multiplyMoneyRaw(string $qty, string $unitCost): string
    {
        return bcmul($qty, $unitCost, 8);
    }

    private function formatMoney(string $value): string
    {
        return number_format((float) $value, self::MONEY_SCALE, '.', '');
    }

    private function formatCost(string $value): string
    {
        return number_format((float) $value, self::COST_SCALE, '.', '');
    }
}