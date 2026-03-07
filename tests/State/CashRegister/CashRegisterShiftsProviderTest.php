<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\State\CashRegister\CashRegisterShiftsProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CashRegisterShiftsProviderTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;

    private CashRegisterShiftsProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider = new CashRegisterShiftsProvider($this->connection);
    }

    /**
     * TC-8: returns a paginator with correct shift data from cash_sessions v2.
     *
     * Session: opening_amount=500.00, CASH_IN=100.00, CASH_OUT=50.00, SALE_IN=300.00
     * closingBalance = 500 + 100 - 50 + 300 = 850.00
     */
    public function test_returns_paginated_shifts_from_cash_sessions(): void
    {
        $cashRegisterId = 3;

        $this->connection
            ->method('fetchOne')
            ->willReturn('1'); // total count

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id'              => '42',
                    'status'          => 'CLOSED',
                    'opening_amount'  => '500.00',
                    'closing_amount'  => '850.00',
                    'opened_at'       => '2026-03-01 08:00:00',
                    'closed_at'       => '2026-03-01 16:00:00',
                    'note'            => 'Test shift',
                    'cashier_username' => 'juan',
                    'cash_in_total'   => '100.00',
                    'cash_out_total'  => '50.00',
                    'sale_in_total'   => '300.00',
                ],
            ]);

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-registers/{id}/shifts'),
            ['id' => $cashRegisterId],
        );

        $this->assertInstanceOf(TraversablePaginator::class, $result);
        $this->assertSame(1.0, $result->getTotalItems());

        $items = iterator_to_array($result);
        $this->assertCount(1, $items);

        $shift = $items[0];
        $this->assertIsArray($shift);
        $this->assertSame(42, $shift['id']);
        $this->assertSame('closed', $shift['status']);
        $this->assertSame('500.00', $shift['openingBalance']);
        $this->assertSame('850.00', $shift['closingBalance']);
        $this->assertSame('850.00', $shift['expectedAmount']);
        $this->assertSame('850.00', $shift['countedAmount']);
        $this->assertSame('0.00', $shift['difference']);
        $this->assertSame('Test shift', $shift['note']);
        $this->assertSame('juan', $shift['cashier']);
        $this->assertNotNull($shift['openedAt']);
        $this->assertNotNull($shift['closedAt']);
    }

    /**
     * TC-8b: returns empty paginator when no sessions found for the register.
     */
    public function test_returns_empty_paginator_when_no_sessions(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturn('0');

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-registers/{id}/shifts'),
            ['id' => 99],
        );

        $this->assertInstanceOf(TraversablePaginator::class, $result);
        $this->assertSame(0.0, $result->getTotalItems());
        $this->assertCount(0, iterator_to_array($result));
    }

    /**
     * TC-8c: open session with no closing_amount has calculated closingBalance and null countedAmount/difference.
     *
     * opening=200.00, CASH_IN=0, CASH_OUT=0, SALE_IN=50.00
     * expected/closingBalance = 250.00
     */
    public function test_open_session_has_calculated_closing_balance(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturn('1');

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id'              => '7',
                    'status'          => 'OPEN',
                    'opening_amount'  => '200.00',
                    'closing_amount'  => null,
                    'opened_at'       => '2026-03-07 09:00:00',
                    'closed_at'       => null,
                    'note'            => null,
                    'cashier_username' => 'maria',
                    'cash_in_total'   => '0.00',
                    'cash_out_total'  => '0.00',
                    'sale_in_total'   => '50.00',
                ],
            ]);

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-registers/{id}/shifts'),
            ['id' => 1],
        );

        $items = iterator_to_array($result);
        $shift = $items[0];

        $this->assertSame('open', $shift['status']);
        $this->assertSame('200.00', $shift['openingBalance']);
        $this->assertSame('250.00', $shift['closingBalance']);
        $this->assertSame('250.00', $shift['expectedAmount']);
        $this->assertNull($shift['countedAmount']);
        $this->assertNull($shift['difference']);
        $this->assertNull($shift['closedAt']);
        $this->assertSame('maria', $shift['cashier']);
    }
}
