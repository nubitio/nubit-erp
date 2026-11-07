<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Report\PayablesAgingCalculator;
use PHPUnit\Framework\TestCase;

final class PayablesAgingCalculatorTest extends TestCase
{
    private const string AS_OF = '2026-11-06';

    public function testBucketsByDaysPastDueAndTotalsPerSupplier(): void
    {
        $result = new PayablesAgingCalculator()->calculate(
            [
                $this->bill('s1', 'ACME', 'PEN', '2026-11-20', '100.00'), // not yet due
                $this->bill('s1', 'ACME', 'PEN', '2026-11-06', '10.00'), // due today -> not due
                $this->bill('s1', 'ACME', 'PEN', '2026-10-20', '200.00'), // 17 days
                $this->bill('s1', 'ACME', 'PEN', '2026-09-20', '300.00'), // 47 days
                $this->bill('s1', 'ACME', 'PEN', '2026-08-20', '400.00'), // 78 days
                $this->bill('s1', 'ACME', 'PEN', '2026-06-01', '500.00'), // 158 days
            ],
            self::AS_OF,
        );

        self::assertCount(1, $result['suppliers']);
        $row = $result['suppliers'][0];
        self::assertSame('110.00', $row['not_due']);
        self::assertSame('200.00', $row['1-30']);
        self::assertSame('300.00', $row['31-60']);
        self::assertSame('400.00', $row['61-90']);
        self::assertSame('500.00', $row['90+']);
        self::assertSame('1510.00', $row['total']);
        self::assertSame(6, $row['billCount']);
    }

    public function testBoundaryDaysFallInTheLowerBucket(): void
    {
        $result = new PayablesAgingCalculator()->calculate(
            [
                $this->bill('s1', 'ACME', 'PEN', '2026-10-07', '1.00'), // 30 days
                $this->bill('s1', 'ACME', 'PEN', '2026-10-06', '2.00'), // 31 days
                $this->bill('s1', 'ACME', 'PEN', '2026-09-07', '4.00'), // 60 days
                $this->bill('s1', 'ACME', 'PEN', '2026-08-08', '8.00'), // 90 days
                $this->bill('s1', 'ACME', 'PEN', '2026-08-07', '16.00'), // 91 days
            ],
            self::AS_OF,
        );

        $row = $result['suppliers'][0];
        self::assertSame('1.00', $row['1-30']);
        self::assertSame('6.00', $row['31-60']);
        self::assertSame('8.00', $row['61-90']);
        self::assertSame('16.00', $row['90+']);
    }

    public function testCurrenciesAreNeverAddedTogether(): void
    {
        $result = new PayablesAgingCalculator()->calculate([
            $this->bill('s1', 'ACME', 'PEN', '2026-10-20', '100.00'),
            $this->bill('s1', 'ACME', 'USD', '2026-10-20', '50.00'),
        ], self::AS_OF);

        self::assertCount(2, $result['suppliers']);
        self::assertSame(['PEN', 'USD'], array_column($result['totals'], 'currency'));
        self::assertSame(['100.00', '50.00'], array_column($result['totals'], 'total'));
    }

    public function testSkipsSettledAndFutureIssuedBills(): void
    {
        $result = new PayablesAgingCalculator()->calculate([
            $this->bill('s1', 'ACME', 'PEN', '2026-10-20', '0.00'),
            $this->bill('s1', 'ACME', 'PEN', '2026-10-20', '100.00', issueDate: '2026-11-30'),
        ], self::AS_OF);

        self::assertSame([], $result['detail']);
        self::assertSame([], $result['suppliers']);
        self::assertSame([], $result['totals']);
    }

    public function testDetailIsSortedAndCarriesDaysOverdue(): void
    {
        $result = new PayablesAgingCalculator()->calculate([
            $this->bill('s2', 'ZETA', 'PEN', '2026-10-20', '5.00'),
            $this->bill('s1', 'ACME', 'PEN', '2026-10-25', '7.00'),
            $this->bill('s1', 'ACME', 'PEN', '2026-10-10', '9.00'),
        ], self::AS_OF);

        self::assertSame(['ACME', 'ACME', 'ZETA'], array_column($result['detail'], 'supplier'));
        self::assertSame([27, 12, 17], array_column($result['detail'], 'daysOverdue'));
        self::assertSame('21.00', $result['totals'][0]['total']);
    }

    /** @return array{supplierId: string, ruc: string, supplier: string, currency: string, document: string, issueDate: string, dueDate: string, balanceDue: string} */
    private function bill(
        string $supplierId,
        string $supplier,
        string $currency,
        string $dueDate,
        string $balance,
        string $issueDate = '2026-06-01',
    ): array {
        return [
            'supplierId' => $supplierId,
            'ruc' => '20123456789',
            'supplier' => $supplier,
            'currency' => $currency,
            'document' => '01 F001-1',
            'issueDate' => $issueDate,
            'dueDate' => $dueDate,
            'balanceDue' => $balance,
        ];
    }
}
