<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\GetCollection;
use App\Dto\CashRegister\CashShiftClosureSummaryDto;
use App\State\CashRegister\CashShiftClosureSummaryProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CashShiftClosureSummaryProviderTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;

    private CashShiftClosureSummaryProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider = new CashShiftClosureSummaryProvider($this->connection);
    }

    /**
     * TC-5: returns cash + non-cash rows from v2 cash_movements exclusively.
     *
     * opening_amount = 100.00
     * CASH_IN = 50.00, CASH_OUT = 10.00
     * SALE_IN cash (id=1) = 200.00, card (id=2) = 150.00
     *
     * cash expected  = 100 + 50 - 10 + 200 = 340.00
     * card expected  = 150.00
     */
    public function test_returns_cash_and_card_rows(): void
    {
        $sessionId = 42;

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['id' => $sessionId, 'opening_amount' => '100.00']);

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(
                static function (string $sql, array $params): string {
                    if (str_contains($sql, "movement_type = 'CASH_IN'")) {
                        return '50.00';
                    }
                    if (str_contains($sql, "movement_type = 'CASH_OUT'")) {
                        return '10.00';
                    }
                    if (str_contains($sql, 'payment_method') && $params[0] === 1) {
                        return 'Efectivo';
                    }
                    if (str_contains($sql, 'payment_method') && $params[0] === 2) {
                        return 'Tarjeta';
                    }

                    return '0';
                }
            );

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['payment_method_id' => 1, 'total' => '200.00'],
                ['payment_method_id' => 2, 'total' => '150.00'],
            ]);

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-shifts/{id}/summary'),
            ['id' => $sessionId],
        );

        $this->assertCount(2, $result);

        /** @var CashShiftClosureSummaryDto $cashRow */
        $cashRow = $result[0];
        $this->assertInstanceOf(CashShiftClosureSummaryDto::class, $cashRow);
        $this->assertSame(1, $cashRow->paymentMethodId);
        $this->assertSame('Efectivo', $cashRow->paymentMethod);
        $this->assertSame('340.00', $cashRow->expectedAmount);

        /** @var CashShiftClosureSummaryDto $cardRow */
        $cardRow = $result[1];
        $this->assertInstanceOf(CashShiftClosureSummaryDto::class, $cardRow);
        $this->assertSame(2, $cardRow->paymentMethodId);
        $this->assertSame('Tarjeta', $cardRow->paymentMethod);
        $this->assertSame('150.00', $cardRow->expectedAmount);
    }

    /**
     * TC-6: when no cash_session exists with the given id, returns empty array.
     */
    public function test_returns_empty_when_session_not_found(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false); // no session found

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-shifts/{id}/summary'),
            ['id' => 999],
        );

        $this->assertSame([], $result);
    }

    /**
     * TC-6b: when session exists but has no movements, cash expected = opening amount.
     *
     * opening = 80.00, CASH_IN = 0, CASH_OUT = 0, SALE_IN = 0
     * cash expected = 80.00
     */
    public function test_cash_expected_equals_opening_when_no_movements(): void
    {
        $sessionId = 10;

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['id' => $sessionId, 'opening_amount' => '80.00']);

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(
                static function (string $sql, array $params): string {
                    if (str_contains($sql, 'payment_method')) {
                        return 'Efectivo';
                    }

                    return '0';
                }
            );

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]); // no SALE_IN movements

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-shifts/{id}/summary'),
            ['id' => $sessionId],
        );

        $this->assertCount(1, $result);

        /** @var CashShiftClosureSummaryDto $cashRow */
        $cashRow = $result[0];
        $this->assertInstanceOf(CashShiftClosureSummaryDto::class, $cashRow);
        $this->assertSame(1, $cashRow->paymentMethodId);
        $this->assertSame('Efectivo', $cashRow->paymentMethod);
        $this->assertSame('80.00', $cashRow->expectedAmount);
    }
}
