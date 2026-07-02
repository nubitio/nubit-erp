<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Get;
use App\CashRegister\Dto\CashSessionXReportDto;
use App\CashRegister\State\CashSessionXReportProvider;
use Doctrine\DBAL\Connection;
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
        $this->provider = new CashSessionXReportProvider($this->connection);
    }

    /**
     * TC-7: X-report calculates all monetary values correctly using brick/math.
     *
     * opening_amount = 200.00
     * V2 SALE_IN total = 500.00 (cash 300.00 + card 200.00)
     * V2 CASH_IN = 50.00, CASH_OUT = 20.00
     * current balance = 200 + 500 + 50 - 20 = 730.00
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
                    return '500.00';
                }
                // CASH_IN total
                if (str_contains($sql, "movement_type = 'CASH_IN'")) {
                    return '50.00';
                }
                // CASH_OUT total
                if (str_contains($sql, "movement_type = 'CASH_OUT'")) {
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
                ['paymentMethodId' => 1, 'paymentMethod' => 'Efectivo', 'amount' => '300.00'],
                ['paymentMethodId' => 2, 'paymentMethod' => 'Tarjeta', 'amount' => '200.00'],
            ]);

        $result = $this->provider->provide(new Get(uriTemplate: '/cash-shifts/{id}/x-report'), ['id' => $cashShiftId]);

        $this->assertInstanceOf(CashSessionXReportDto::class, $result);
        $this->assertSame('200.00', $result->openingBalance);
        $this->assertSame('500.00', $result->totalSales);
        $this->assertSame('50.00', $result->cashIn);
        $this->assertSame('20.00', $result->cashOut);
        $this->assertSame('730.00', $result->currentBalance);
        $this->assertSame(60, $result->shiftDurationMinutes);

        $this->assertCount(2, $result->salesByPaymentMethod);

        $this->assertSame(1, $result->salesByPaymentMethod[0]->paymentMethodId);
        $this->assertSame('Efectivo', $result->salesByPaymentMethod[0]->paymentMethod);
        $this->assertSame('300.00', $result->salesByPaymentMethod[0]->amount);

        $this->assertSame(2, $result->salesByPaymentMethod[1]->paymentMethodId);
        $this->assertSame('Tarjeta', $result->salesByPaymentMethod[1]->paymentMethod);
        $this->assertSame('200.00', $result->salesByPaymentMethod[1]->amount);
    }
}
