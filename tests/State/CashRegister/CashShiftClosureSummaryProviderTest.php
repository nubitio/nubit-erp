<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\GetCollection;
use App\Dto\CashRegister\CashShiftClosureSummaryDto;
use App\State\CashRegister\CashShiftClosureSummaryProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
     * TC-5: returns cash + non-cash rows when V2 session exists with multiple payment methods.
     *
     * opening_amount = 100.00
     * legacy income = 50.00, expense = 10.00
     * V2 SALE_IN cash (id=1) = 200.00, card (id=2) = 150.00
     *
     * cash expected  = 100 + 50 - 10 + 200 = 340.00
     * card expected  = 150.00
     */
    public function test_returns_cash_and_card_rows(): void
    {
        $cashShiftId = 1;
        $sessionId = 42;

        $this->connection
            ->method('fetchAssociative')
            ->willReturnCallback(
                static function (string $sql, array $params) use ($sessionId): array {
                    return ['id' => $sessionId, 'opening_amount' => '100.00'];
                }
            );

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(
                static function (string $sql, array $params): string {
                    if (str_contains($sql, "type = 'income'")) {
                        return '50.00';
                    }
                    if (str_contains($sql, "type = 'expense'")) {
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
            ['id' => $cashShiftId],
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
     * TC-6: when no V2 cash_session exists (legacy data only), returns a single cash row
     * with amount derived from legacy movements only.
     *
     * legacy income = 80.00, expense = 0.00
     * cash expected = 80.00
     */
    public function test_fallback_when_no_cash_session(): void
    {
        $cashShiftId = 99;

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false); // no session found

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(
                static function (string $sql, array $params): string {
                    if (str_contains($sql, "type = 'income'")) {
                        return '80.00';
                    }
                    if (str_contains($sql, "type = 'expense'")) {
                        return '0.00';
                    }

                    return 'Efectivo';
                }
            );

        $result = $this->provider->provide(
            new GetCollection(uriTemplate: '/cash-shifts/{id}/summary'),
            ['id' => $cashShiftId],
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
