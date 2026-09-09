<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Service\CustomerAccountTimeline;
use PHPUnit\Framework\TestCase;

final class CustomerAccountTimelineTest extends TestCase
{
    public function testMergeWalksChargesAndPaymentsChronologicallyWithARunningBalance(): void
    {
        $timeline = CustomerAccountTimeline::merge(charges: [
            $this->charge('s1', '2026-01-10', '500.00', 'F001-1'),
            $this->charge('s2', '2026-02-01', '300.00', 'F001-2'),
        ], payments: [
            $this->payment('s1', 11, '2026-01-20', '200.00', 'F001-1'),
            $this->payment('s2', 12, '2026-02-01', '300.00', 'F001-2'),
        ]);

        self::assertSame(['charge', 'payment', 'charge', 'payment'], array_column($timeline, 'entry_type'));
        // 0 +500 => 500 ; -200 => 300 ; +300 => 600 (charge before same-day payment) ; -300 => 300
        self::assertSame(['500.00', '300.00', '600.00', '300.00'], array_column($timeline, 'running_balance'));
        self::assertSame('500.00', $timeline[0]['debit']);
        self::assertNull($timeline[0]['sale_payment_id']);
        self::assertSame('200.00', $timeline[1]['credit']);
        self::assertSame(11, $timeline[1]['sale_payment_id']);
    }

    public function testMergeCanGoNegativeWhenPaymentsLeadCharges(): void
    {
        $timeline = CustomerAccountTimeline::merge(charges: [$this->charge(
            's1',
            '2026-03-05',
            '100.00',
            'F001-9',
        )], payments: [$this->payment('s1', 1, '2026-03-01', '150.00', 'F001-9')]);

        self::assertSame(['-150.00', '-50.00'], array_column($timeline, 'running_balance'));
    }

    public function testMergeIsEmptyWithoutEvents(): void
    {
        self::assertSame([], CustomerAccountTimeline::merge([], []));
    }

    /**
     * @return array{customer_id: int, sale_id: string, occurred_on: string, amount: string, document: string}
     */
    private function charge(string $saleId, string $on, string $amount, string $doc): array
    {
        return [
            'customer_id' => 9,
            'sale_id' => $saleId,
            'occurred_on' => $on,
            'amount' => $amount,
            'document' => $doc,
        ];
    }

    /**
     * @return array{customer_id: int, sale_id: string, sale_payment_id: int, occurred_on: string, amount: string, document: string}
     */
    private function payment(string $saleId, int $paymentId, string $on, string $amount, string $doc): array
    {
        return [
            'customer_id' => 9,
            'sale_id' => $saleId,
            'sale_payment_id' => $paymentId,
            'occurred_on' => $on,
            'amount' => $amount,
            'document' => $doc,
        ];
    }
}
