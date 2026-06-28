<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\Service;

use App\Purchasing\Service\PurchaseReceiptSequenceService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class PurchaseReceiptSequenceServiceTest extends TestCase
{
    public function testReturnsAllocatedSequenceNumber(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement');
        $connection->method('fetchOne')->willReturn('7');

        $service = new PurchaseReceiptSequenceService($connection);

        self::assertSame(7, $service->next());
    }
}