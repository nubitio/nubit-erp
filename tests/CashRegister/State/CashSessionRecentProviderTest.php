<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\GetCollection;
use App\CashRegister\State\CashSessionRecentProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CashSessionRecentProviderTest extends TestCase
{
    public function testWithoutBranchThereAreNoShifts(): void
    {
        $provider = new CashSessionRecentProvider($this->createStub(Connection::class), $this->branch(null));

        self::assertSame([], $provider->provide(new GetCollection()));
    }

    public function testMapsOpenAndClosedShifts(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => '12',
                    'cash_register_id' => '1',
                    'register_name' => 'Caja 01',
                    'cashier' => 'rosa',
                    'opened_at' => '2026-11-23 08:02:00',
                    'closed_at' => null,
                    'status' => 'OPEN',
                    'opening_amount' => '200',
                    'difference_amount' => null,
                    'sales' => '7547',
                ],
                [
                    'id' => '11',
                    'cash_register_id' => '3',
                    'register_name' => 'Caja 03',
                    'cashier' => null,
                    'opened_at' => '2026-11-22 08:00:00',
                    'closed_at' => '2026-11-22 19:34:00',
                    'status' => 'CLOSED',
                    'opening_amount' => '200.00',
                    'difference_amount' => '-5.00',
                    'sales' => '5912.4',
                ],
            ]);

        $result = new CashSessionRecentProvider($connection, $this->branch('branch-1'))->provide(new GetCollection());

        self::assertCount(2, $result);
        self::assertSame('open', $result[0]->status);
        self::assertSame('200.00', $result[0]->openingAmount);
        self::assertSame('7547.00', $result[0]->sales);
        self::assertNull($result[0]->difference);
        self::assertNull($result[0]->closedAt);
        self::assertSame('closed', $result[1]->status);
        self::assertSame('-5.00', $result[1]->difference);
        self::assertNull($result[1]->cashier);
    }

    private function branch(?string $id): BranchContextServiceInterface
    {
        $branch = $this->createStub(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn($id);

        return $branch;
    }
}
