<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Get;
use App\CashRegister\Dto\CashSessionXReportDto;
use App\CashRegister\Service\SessionSalesBreakdown;
use App\CashRegister\State\CashSessionClosureSummaryProvider;
use App\CashRegister\State\CashSessionXReportProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CashSessionXReportProviderTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;

    private CashSessionXReportProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $breakdown = $this->createStub(SessionSalesBreakdown::class);
        $breakdown
            ->method('forSession')
            ->willReturn([
                'methods' => [
                    ['id' => 1, 'name' => 'Efectivo', 'amount' => '300.00', 'operations' => 4],
                    ['id' => 2, 'name' => 'Tarjeta', 'amount' => '200.00', 'operations' => 2],
                ],
                'receivable' => ['amount' => '40.00', 'operations' => 1],
                'total' => '540.00',
            ]);
        $this->provider = new CashSessionXReportProvider($this->connection, $breakdown, $this->branchContext());
    }

    /**
     * TC-7: X-report calculates all monetary values correctly using brick/math.
     *
     * opening_amount = 200.00
     * drawer SALE_IN = 300.00 (only cash moves the drawer); the shift sold 540.00: cash 300 + card 200 + credit 40
     * V2 CASH_IN = 50.00, CASH_OUT = 20.00
     * current balance (the drawer) = 200 + 300 + 50 - 20 = 530.00
     * shiftDurationMinutes: provided as fixed 60 by mock
     */
    public function test_x_report_calculates_correctly(): void
    {
        $cashShiftId = 5;
        $sessionId = 10;

        $this->connection
            ->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use ($sessionId): array {
                return ['id' => $sessionId, 'opening_amount' => '200.00'];
            });

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params): string|int {
                // SALE_IN total
                if (str_contains($sql, "movement_type = 'SALE_IN'")) {
                    return '300.00';
                }
                // CASH_IN total
                if (str_contains($sql, "movement_type = 'CASH_IN'")) {
                    return '50.00';
                }
                // CASH_OUT total
                if (str_contains($sql, "movement_type IN ('CASH_OUT', 'REFUND_OUT')")) {
                    return '20.00';
                }
                // Shift duration
                if (str_contains($sql, 'TIMESTAMPDIFF')) {
                    return 60;
                }

                return '0';
            });

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['paymentMethodId' => 1, 'paymentMethod' => 'Efectivo', 'amount' => '300.00', 'operations' => '4'],
                ['paymentMethodId' => 2, 'paymentMethod' => 'Tarjeta', 'amount' => '200.00', 'operations' => '2'],
            ]);

        $result = $this->provider->provide(new Get(uriTemplate: '/cash-shifts/{id}/x-report'), ['id' => $cashShiftId]);

        $this->assertInstanceOf(CashSessionXReportDto::class, $result);
        $this->assertSame('200.00', $result->openingBalance);
        $this->assertSame('540.00', $result->totalSales, 'what the shift sold, credit included');
        $this->assertSame('40.00', $result->receivableTotal);
        $this->assertSame(1, $result->receivableOperations);
        $this->assertSame('50.00', $result->cashIn);
        $this->assertSame('20.00', $result->cashOut);
        $this->assertSame('530.00', $result->currentBalance, 'the drawer, built from cash only');
        $this->assertSame(60, $result->shiftDurationMinutes);

        $this->assertCount(2, $result->salesByPaymentMethod);

        $this->assertSame(1, $result->salesByPaymentMethod[0]->paymentMethodId);
        $this->assertSame('Efectivo', $result->salesByPaymentMethod[0]->paymentMethod);
        $this->assertSame('300.00', $result->salesByPaymentMethod[0]->amount);
        $this->assertSame(4, $result->salesByPaymentMethod[0]->operations);

        $this->assertSame(2, $result->salesByPaymentMethod[1]->paymentMethodId);
        $this->assertSame('Tarjeta', $result->salesByPaymentMethod[1]->paymentMethod);
        $this->assertSame('200.00', $result->salesByPaymentMethod[1]->amount);
    }

    public function testCashBreakdownMatchesClosureWhenCashAndDigitalRefundsCoexist(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE cash_sessions (id INTEGER, opening_amount NUMERIC, opened_at TEXT, branch_id TEXT)');
        $db->executeStatement(
            'CREATE TABLE cash_movements (cash_session_id INTEGER, category_id INTEGER, payment_method_id INTEGER, movement_type TEXT, amount NUMERIC)',
        );
        $db->executeStatement('CREATE TABLE cash_movement_category (id INTEGER, code TEXT)');
        $db->executeStatement("INSERT INTO cash_movement_category VALUES (1, 'manager_withdrawal')");
        $db->executeStatement('CREATE TABLE tax_document (tax_document_id INTEGER, sale_id TEXT, document_type TEXT)');
        $db->executeStatement('CREATE TABLE payment_method (id INTEGER, name TEXT)');
        $db->executeStatement("INSERT INTO cash_sessions VALUES (1, 200.00, '2026-10-29 08:00:00', 'branch-a')");
        $db->executeStatement("INSERT INTO payment_method VALUES (1, 'Efectivo'), (2, 'Tarjeta')");
        // The shift sold 540: 300 in cash, 200 by card, 40 on credit (15 of it collected later, in another shift's
        // hands), and a voided 50 by card that counts nowhere. Only the cash reached the drawer.
        $db->executeStatement(
            'CREATE TABLE sales (id TEXT, cash_session_id INTEGER, grand_total NUMERIC, status TEXT, deleted_at TEXT, receivable_account INTEGER)',
        );
        $db->executeStatement(
            'CREATE TABLE sale_payments (id INTEGER PRIMARY KEY, sale_id TEXT, payment_method_id INTEGER, amount NUMERIC, idempotency_key TEXT)',
        );
        $db->executeStatement("INSERT INTO sales VALUES ('a', 1, 300, 'COMPLETED', NULL, 0), ('b', 1, 200, 'COMPLETED', NULL, 0),
                ('c', 1, 50, 'VOIDED', NULL, 0), ('d', 1, 40, 'COMPLETED', NULL, 1)");
        $db->executeStatement(
            "INSERT INTO sale_payments (sale_id, payment_method_id, amount, idempotency_key) VALUES
                ('a', 1, 300, 'k-a'), ('b', 2, 200, 'k-b'), ('c', 2, 50, 'k-c'), ('d', 1, 15, NULL)",
        );
        foreach ([
            [1, 'SALE_IN',    '300.00'],
            [1, 'CASH_IN',    '50.00'],
            [1, 'CASH_OUT',   '20.00'],
            [1, 'REFUND_OUT', '10.00'],
            [2, 'REFUND_OUT', '30.00'],
        ] as [$method, $type, $amount]) {
            $db->insert('cash_movements', [
                'cash_session_id' => 1,
                'payment_method_id' => $method,
                'movement_type' => $type,
                'amount' => $amount,
            ]);
        }
        // A withdrawal to the safe (the 20.00 CASH_OUT) and the receipts of the shift: a boleta and a factura count,
        // the one of the voided sale does not.
        $db->executeStatement("UPDATE cash_movements SET category_id = 1 WHERE movement_type = 'CASH_OUT'");
        $db->executeStatement("INSERT INTO tax_document VALUES (1, 'a', '03'), (2, 'b', '01'), (3, 'c', '03')");
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(fn(string $sql, array $params) => $db->fetchAssociative($sql, self::named($params)));
        $connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(fn(string $sql, array $params) => $db->fetchAllAssociative(
                $sql,
                self::named($params),
            ));
        $connection->method('fetchOne')->willReturnCallback(fn(string $sql, array $params) => str_contains(
            $sql,
            'TIMESTAMPDIFF',
        )
                ? 60
                : $db->fetchOne($sql, self::named($params)));
        $breakdown = new SessionSalesBreakdown($connection);
        $report = new CashSessionXReportProvider($connection, $breakdown, $this->branchContext())->provide(new Get(), ['id' => 1]);
        $closure = new CashSessionClosureSummaryProvider($connection, $breakdown, $this->branchContext())->provide(new Get(), ['id' => 1]);
        self::assertSame('290.00', $report->cashSalesNet);
        self::assertSame('20.00', $report->cashAdjustmentsOut);
        self::assertSame('60.00', $report->cashOut);
        self::assertSame('20.00', $report->withdrawalsOut, 'what went to the safe, inside cashOut');
        self::assertSame(['boletas' => 1, 'facturas' => 1, 'creditNotes' => 0], $report->documents);
        self::assertSame('520.00', $closure[0]->expectedAmount);
        self::assertSame(
            $closure[0]->expectedAmount,
            \Brick\Math\BigDecimal::of($report->openingBalance)
                ->plus($report->cashSalesNet)
                ->plus($report->cashIn)
                ->minus($report->cashAdjustmentsOut)
                ->toScale(2)
                ->__toString(),
        );
        self::assertSame('200.00', $closure[1]->expectedAmount, 'the card: what the shift sold with it');
        self::assertCount(2, $closure, 'the voided card sale adds no row of its own');
        self::assertSame('540.00', $report->totalSales);
        self::assertSame(
            '40.00',
            $report->receivableTotal,
            'the later collection of 15 is not part of what was left on credit',
        );
        self::assertSame(1, $report->receivableOperations);
        self::assertSame(
            [1, 1],
            array_map(static fn($row): int => $row->operations, $report->salesByPaymentMethod),
            'refunds and cash movements are not counted as sales operations',
        );
        $db->close();
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function named(array $params): array
    {
        $named = [];
        foreach ($params as $key => $value) {
            $named[(string) $key] = $value;
        }

        return $named;
    }
    private function branchContext(): BranchContextServiceInterface
    {
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn('branch-a');

        return $context;
    }
}
