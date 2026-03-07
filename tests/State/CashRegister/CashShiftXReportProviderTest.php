<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\Get;
use App\Dto\CashRegister\CashShiftXReportDto;
use App\State\CashRegister\CashShiftXReportProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CashShiftXReportProviderTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;

    private CashShiftXReportProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider = new CashShiftXReportProvider($this->connection);
    }

    /**
     * TC-7: X-report calculates all monetary values from v2 tables exclusively.
     *
     * opening_amount = 200.00
     * SALE_IN total  = 500.00 (cash 300.00 + card 200.00)
     * CASH_IN total  = 50.00
     * CASH_OUT total = 20.00
     * current balance = 200 + 500 + 50 - 20 = 730.00
     * shiftDurationMinutes: provided as fixed 60 by mock
     */
    public function test_x_report_calculates_correctly(): void
    {
        $sessionId = 5;

        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(
                static function (string $sql, array $params): string|int {
                    if (str_contains($sql, 'opening_amount')) {
                        return '200.00';
                    }
                    if (str_contains($sql, "movement_type = 'SALE_IN'")) {
                        return '500.00';
                    }
                    if (str_contains($sql, "movement_type = 'CASH_IN'")) {
                        return '50.00';
                    }
                    if (str_contains($sql, "movement_type = 'CASH_OUT'")) {
                        return '20.00';
                    }
                    if (str_contains($sql, 'TIMESTAMPDIFF')) {
                        return 60;
                    }

                    return '0';
                }
            );

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['paymentMethodId' => 1, 'paymentMethod' => 'Efectivo', 'amount' => '300.00'],
                ['paymentMethodId' => 2, 'paymentMethod' => 'Tarjeta',  'amount' => '200.00'],
            ]);

        $result = $this->provider->provide(
            new Get(uriTemplate: '/cash-shifts/{id}/x-report'),
            ['id' => $sessionId],
        );

        $this->assertInstanceOf(CashShiftXReportDto::class, $result);
        $this->assertSame('200.00', $result->openingBalance);
        $this->assertSame('500.00', $result->totalSales);
        $this->assertSame('50.00', $result->cashIn);
        $this->assertSame('20.00', $result->cashOut);
        $this->assertSame('730.00', $result->currentBalance);
        $this->assertSame(60, $result->shiftDurationMinutes);

        $this->assertCount(2, $result->salesByPaymentMethod);

        $this->assertSame(1, $result->salesByPaymentMethod[0]['paymentMethodId']);
        $this->assertSame('Efectivo', $result->salesByPaymentMethod[0]['paymentMethod']);
        $this->assertSame('300.00', $result->salesByPaymentMethod[0]['amount']);

        $this->assertSame(2, $result->salesByPaymentMethod[1]['paymentMethodId']);
        $this->assertSame('Tarjeta', $result->salesByPaymentMethod[1]['paymentMethod']);
        $this->assertSame('200.00', $result->salesByPaymentMethod[1]['amount']);
    }

    /**
     * TC-7b: X-report returns zero-value DTO when session is not found.
     */
    public function test_x_report_returns_zeros_when_session_not_found(): void
    {
        $this->connection
            ->method('fetchOne')
            ->willReturn(false);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->provider->provide(
            new Get(uriTemplate: '/cash-shifts/{id}/x-report'),
            ['id' => 999],
        );

        $this->assertInstanceOf(CashShiftXReportDto::class, $result);
        $this->assertSame('0.00', $result->openingBalance);
        $this->assertSame('0.00', $result->totalSales);
        $this->assertSame('0.00', $result->cashIn);
        $this->assertSame('0.00', $result->cashOut);
        $this->assertSame('0.00', $result->currentBalance);
        $this->assertCount(0, $result->salesByPaymentMethod);
    }
}
