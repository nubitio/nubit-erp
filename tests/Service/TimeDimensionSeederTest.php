<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Reporting\Service\TimeDimensionSeeder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class TimeDimensionSeederTest extends TestCase
{
    public function testSeedInsertsRowsInBatchesAndInvokesProgressCallback(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');
        $connection->expects(self::exactly(2))->method('executeStatement');

        $seeder = new TimeDimensionSeeder($connection);

        $progress = [];
        $count = $seeder->seed(
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-04'),
            batchSize: 2,
            locale: 'en_US',
            progressCallback: static function (int $batchCount) use (&$progress): void {
                $progress[] = $batchCount;
            }
        );

        self::assertSame(3, $count);
        self::assertSame([2, 1], $progress);
    }

    public function testSeedRollsBackTransactionWhenInsertFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $connection->method('executeStatement')->willThrowException(new \RuntimeException('insert failed'));

        $seeder = new TimeDimensionSeeder($connection);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('insert failed');

        $seeder->seed(
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-02'),
            batchSize: 1,
            locale: 'en_US'
        );
    }
}
