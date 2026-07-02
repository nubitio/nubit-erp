<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Inventory\Core\Service\StockRebuilderJob;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class StockRebuilderJobTest extends TestCase
{
    public function testRebuildExecutesInsertAndReturnsCount(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('stock_items'), self::arrayHasKey('warehouse_id'))
            ->willReturn(5);

        $job = new StockRebuilderJob($conn);
        $count = $job->rebuild('wh-1', 'prod-1');

        self::assertSame(5, $count);
    }

    public function testRebuildPassesNullFilters(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::anything(), ['warehouse_id' => null, 'product_id' => null])
            ->willReturn(42);

        $job = new StockRebuilderJob($conn);
        $count = $job->rebuild();

        self::assertSame(42, $count);
    }

    public function testRebuildSqlContainsGroupBy(): void
    {
        $capturedSql = '';
        $conn = $this->createStub(Connection::class);
        $conn->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$capturedSql): int {
            $capturedSql = $sql;

            return 0;
        });

        new StockRebuilderJob($conn)->rebuild();

        self::assertStringContainsString('GROUP BY', $capturedSql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $capturedSql);
    }
}
