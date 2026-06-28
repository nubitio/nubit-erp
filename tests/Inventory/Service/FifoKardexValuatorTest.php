<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Service;

use App\Inventory\Service\FifoKardexValuator;
use PHPUnit\Framework\TestCase;

final class FifoKardexValuatorTest extends TestCase
{
    private FifoKardexValuator $valuator;

    protected function setUp(): void
    {
        $this->valuator = new FifoKardexValuator();
    }

    public function testApplyConsumesOldestLayersFirst(): void
    {
        $results = $this->valuator->apply([
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '100.000000',
                'inbound_unit_cost' => '10.0000',
            ],
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '-30.000000',
                'inbound_unit_cost' => null,
            ],
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '9.000000',
                'inbound_unit_cost' => '15.0000',
            ],
        ]);

        self::assertSame('10.0000', $results[0]['unit_cost']);
        self::assertSame('1000.00', $results[0]['line_value']);
        self::assertSame('1000.00', $results[0]['running_value']);

        self::assertSame('10.0000', $results[1]['unit_cost']);
        self::assertSame('300.00', $results[1]['line_value']);
        self::assertSame('700.00', $results[1]['running_value']);

        self::assertSame('15.0000', $results[2]['unit_cost']);
        self::assertSame('135.00', $results[2]['line_value']);
        self::assertSame('835.00', $results[2]['running_value']);
    }

    public function testEndingMetricsAggregatesAcrossWarehouses(): void
    {
        $metrics = $this->valuator->endingMetrics([
            [
                'warehouse_id' => 'wh1',
                'qty_delta' => '10.000000',
                'inbound_unit_cost' => '5.0000',
            ],
            [
                'warehouse_id' => 'wh2',
                'qty_delta' => '4.000000',
                'inbound_unit_cost' => '8.0000',
            ],
        ]);

        self::assertSame('82.00', $metrics['stockValue']);
        self::assertSame('5.8571', $metrics['avgUnitCost']);
    }
}