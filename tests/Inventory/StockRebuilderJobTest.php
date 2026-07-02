<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Inventory\Core\Service\StockRebuilderJob;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class StockRebuilderJobTest extends TestCase
{
    public function testRebuildUsesLedgerSumAsSourceOfTruth(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::callback(static fn(string $sql): bool => str_contains($sql, 'SUM(sm.qty_delta_base)')), [
                'warehouse_id' => 'wh-1',
                'product_id' => 'prod-1',
            ])
            ->willReturn(1);

        $job = new StockRebuilderJob($connection);

        self::assertSame(1, $job->rebuild('wh-1', 'prod-1'));
    }
}
