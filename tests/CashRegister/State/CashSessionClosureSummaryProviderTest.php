<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\GetCollection;
use App\CashRegister\Dto\CashSessionClosureSummaryDto;
use App\CashRegister\Service\SessionSalesBreakdown;
use App\CashRegister\State\CashSessionClosureSummaryProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CashSessionClosureSummaryProviderTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;

    private CashSessionClosureSummaryProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        // The card was sold in the shift; only cash moves the drawer, so it is not in the movements below.
        $breakdown = $this->createStub(SessionSalesBreakdown::class);
        $breakdown
            ->method('forSession')
            ->willReturn([
                'methods' => [
                    ['id' => 1, 'name' => 'Efectivo', 'amount' => '200.00', 'operations' => 3],
                    ['id' => 2, 'name' => 'Tarjeta', 'amount' => '150.00', 'operations' => 2],
                ],
                'receivable' => ['amount' => '0.00', 'operations' => 0],
                'total' => '350.00',
            ]);
        $this->provider = new CashSessionClosureSummaryProvider($this->connection, $breakdown, $this->branchContext());
    }

    /**
     * TC-5: returns cash + non-cash rows when V2 session exists with multiple payment methods.
     *
     * opening_amount = 100.00
     * V2 CASH_IN = 50.00, CASH_OUT = 10.00
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
            ->willReturnCallback(static function (string $sql, array $params) use ($sessionId): array {
                return ['id' => $sessionId, 'opening_amount' => '100.00'];
            });

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params): string {
                // CASH_IN query
                if (str_contains($sql, "movement_type = 'CASH_IN'")) {
                    return '50.00';
                }
                // CASH_OUT query
                if (str_contains($sql, "movement_type = 'CASH_OUT'")) {
                    return '10.00';
                }
                // payment method name lookup
                if (str_contains($sql, 'payment_method') && isset($params[0])) {
                    return $params[0] === 1 ? 'Efectivo' : 'Tarjeta';
                }

                return '0';
            });

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['payment_method_id' => 1, 'total' => '200.00'],
                ['payment_method_id' => 2, 'total' => '150.00'],
            ]);

        $result = $this->provider->provide(new GetCollection(uriTemplate: '/cash-shifts/{id}/summary'), [
            'id' => $cashShiftId,
        ]);

        $this->assertCount(2, $result);

        /** @var CashSessionClosureSummaryDto $cashRow */
        $cashRow = $result[0];
        $this->assertInstanceOf(CashSessionClosureSummaryDto::class, $cashRow);
        $this->assertSame(1, $cashRow->paymentMethodId);
        $this->assertSame('Efectivo', $cashRow->paymentMethod);
        $this->assertSame('340.00', $cashRow->expectedAmount);

        /** @var CashSessionClosureSummaryDto $cardRow */
        $cardRow = $result[1];
        $this->assertInstanceOf(CashSessionClosureSummaryDto::class, $cardRow);
        $this->assertSame(2, $cardRow->paymentMethodId);
        $this->assertSame('Tarjeta', $cardRow->paymentMethod);
        $this->assertSame('150.00', $cardRow->expectedAmount);
    }

    /**
     * TC-6: when no V2 cash_session exists (both lookups fail),
     * a NotFoundHttpException is thrown — legacy-only sessions are no longer supported.
     */
    public function test_fallback_when_no_cash_session(): void
    {
        $cashShiftId = 99;

        $this->connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('CashSession not found for id 99.');

        $this->provider->provide(new GetCollection(uriTemplate: '/cash-shifts/{id}/summary'), ['id' => $cashShiftId]);
    }
    private function branchContext(): BranchContextServiceInterface
    {
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn('branch-a');

        return $context;
    }
}
