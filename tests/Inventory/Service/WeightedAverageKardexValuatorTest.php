<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Service;

use App\Inventory\Service\WeightedAverageKardexValuator;
use PHPUnit\Framework\TestCase;

final class WeightedAverageKardexValuatorTest extends TestCase
{
    private WeightedAverageKardexValuator $valuator;

    protected function setUp(): void
    {
        $this->valuator = new WeightedAverageKardexValuator();
    }

    public function testApplyUsesWeightedAverageForOutboundMovements(): void
    {
        $results = $this->valuator->apply([
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '100.000000',
                'inbound_unit_cost' => '10.0000',
            ],
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '100.000000',
                'inbound_unit_cost' => '20.0000',
            ],
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '-50.000000',
                'inbound_unit_cost' => null,
            ],
        ]);

        self::assertSame('10.0000', $results[0]['unit_cost']);
        self::assertSame('1000.00', $results[0]['line_value']);

        self::assertSame('20.0000', $results[1]['unit_cost']);
        self::assertSame('2000.00', $results[1]['line_value']);
        self::assertSame('3000.00', $results[1]['running_value']);

        self::assertSame('15.0000', $results[2]['unit_cost']);
        self::assertSame('750.00', $results[2]['line_value']);
        self::assertSame('2250.00', $results[2]['running_value']);
    }

    public function testEndingMetricsReturnsWeightedAverageCost(): void
    {
        $metrics = $this->valuator->endingMetrics([
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '10.000000',
                'inbound_unit_cost' => '5.0000',
            ],
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '10.000000',
                'inbound_unit_cost' => '15.0000',
            ],
        ]);

        self::assertSame('200.00', $metrics['stockValue']);
        self::assertSame('10.0000', $metrics['avgUnitCost']);
    }
}