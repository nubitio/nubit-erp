<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Context\BranchContextInterface;
use App\AI\Tool\StockAlertTool;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StockAlertToolTest extends TestCase
{
    private Connection&MockObject $connection;
    private BranchContextInterface&MockObject $branchContextService;
    private StockAlertTool $tool;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->branchContextService = $this->createMock(BranchContextInterface::class);
        $this->branchContextService->method('getCurrentBranchId')->willReturn(null);

        $this->tool = new StockAlertTool($this->connection, $this->branchContextService);
    }

    /**
     * AC-SA-01: When all products are above minimum stock, returns empty alerts.
     */
    public function testReturnsEmptyAlertsWhenAllStockOk(): void
    {
        // No rows below min stock
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [], // belowMinSql returns nothing
            );

        $result = $this->tool->stockAlert();

        self::assertSame([], $result['alerts']);
        self::assertSame(0, $result['total_below_min']);
    }

    /**
     * AC-SA-02: Results are sorted by urgency (days_to_stockout ASC, nulls last).
     */
    public function testSortsByUrgencyAscendingNullsLast(): void
    {
        $belowMinRows = [
            [
                'product_id' => '1',
                'product_name' => 'Producto A',
                'sku' => 'SKU-A',
                'qty_on_hand' => '5.000',
                'min_stock' => '10.000',
                'unit' => 'UND',
            ],
            [
                'product_id' => '2',
                'product_name' => 'Producto B',
                'sku' => 'SKU-B',
                'qty_on_hand' => '3.000',
                'min_stock' => '10.000',
                'unit' => 'UND',
            ],
            [
                'product_id' => '3',
                'product_name' => 'Producto C',
                'sku' => null,
                'qty_on_hand' => '2.000',
                'min_stock' => '10.000',
                'unit' => 'KG',
            ],
        ];

        // Velocity: A = 1/day (5 days), B = 0.5/day (6 days), C = no sales (null)
        $velocityRows = [
            ['product_id' => '1', 'velocity' => '1.0'],  // 5/1 = 5.0 days
            ['product_id' => '2', 'velocity' => '0.5'],  // 3/0.5 = 6.0 days
            // product '3' has no row → velocity 0 → null
        ];

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($belowMinRows, $velocityRows);

        $result = $this->tool->stockAlert();

        self::assertCount(3, $result['alerts']);
        self::assertSame(3, $result['total_below_min']);

        // First: Producto A (5.0 days), Second: Producto B (6.0 days), Third: Producto C (null)
        self::assertSame('Producto A', $result['alerts'][0]['product_name']);
        self::assertSame(5.0, $result['alerts'][0]['days_to_stockout']);

        self::assertSame('Producto B', $result['alerts'][1]['product_name']);
        self::assertSame(6.0, $result['alerts'][1]['days_to_stockout']);

        self::assertSame('Producto C', $result['alerts'][2]['product_name']);
        self::assertNull($result['alerts'][2]['days_to_stockout']);
    }

    /**
     * AC-SA-03: days_to_stockout is null when a product has no recent sales (velocity=0).
     */
    public function testNullDaysToStockoutWhenNoRecentSales(): void
    {
        $belowMinRows = [
            [
                'product_id' => '42',
                'product_name' => 'Producto Sin Ventas',
                'sku' => 'SKU-X',
                'qty_on_hand' => '8.000',
                'min_stock' => '15.000',
                'unit' => 'UND',
            ],
        ];

        // No velocity rows for product 42
        $velocityRows = [];

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($belowMinRows, $velocityRows);

        $result = $this->tool->stockAlert();

        self::assertCount(1, $result['alerts']);
        self::assertNull($result['alerts'][0]['days_to_stockout']);
        self::assertSame(8.0, $result['alerts'][0]['qty_on_hand']);
        self::assertSame('Producto Sin Ventas', $result['alerts'][0]['product_name']);
    }

    /**
     * AC-SA-04: limit parameter is capped at 100.
     */
    public function testLimitIsCappedAt100(): void
    {
        // Generate 150 rows below min stock
        $rows = [];
        for ($i = 1; $i <= 150; $i++) {
            $rows[] = [
                'product_id' => (string) $i,
                'product_name' => "Producto {$i}",
                'sku' => null,
                'qty_on_hand' => '5.000',
                'min_stock' => '10.000',
                'unit' => 'UND',
            ];
        }

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($rows, []);

        $result = $this->tool->stockAlert(999); // over the max

        self::assertSame(150, $result['total_below_min']);
        // Despite 150 total, result is capped at 100
        self::assertCount(100, $result['alerts']);
    }
}
