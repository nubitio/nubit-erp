<?php

declare(strict_types=1);

namespace App\Tests\Efact\Doctrine\DBAL;

use App\Efact\Doctrine\DBAL\TenantConnection;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use PHPUnit\Framework\TestCase;

final class TenantConnectionTest extends TestCase
{
    public function testSwitchingToTheConnectedTenantDoesNotResetAnActiveTransaction(): void
    {
        $driverConnection = $this->createMock(DriverConnection::class);
        $driverConnection->expects($this->once())->method('beginTransaction');
        $driverConnection->expects($this->once())->method('rollBack');

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())->method('connect')->willReturn($driverConnection);

        $connection = new TenantConnection(
            ['dbname' => 'bootstrap', 'user' => 'bootstrap'],
            $driver,
            new Configuration(),
        );

        $connection->switchTenantParams(['connection' => 'demo']);
        $connection->beginTransaction();
        $connection->switchTenantParams(['connection' => 'demo']);

        static::assertTrue($connection->isTransactionActive());
        static::assertSame(1, $connection->getTransactionNestingLevel());

        $connection->rollBack();
    }

    public function testCloseResetsTransactionState(): void
    {
        $driverConnection = $this->createMock(DriverConnection::class);
        $driverConnection->expects($this->once())->method('beginTransaction');

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())->method('connect')->willReturn($driverConnection);

        $connection = new TenantConnection(
            ['dbname' => 'bootstrap', 'user' => 'bootstrap'],
            $driver,
            new Configuration(),
        );

        $connection->switchTenantParams(['connection' => 'demo']);
        $connection->beginTransaction();
        $connection->close();

        static::assertFalse($connection->isTransactionActive());
    }
}
