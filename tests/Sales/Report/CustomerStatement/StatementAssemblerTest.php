<?php

declare(strict_types=1);

namespace App\Tests\Sales\Report\CustomerStatement;

use App\Sales\Report\CustomerStatement\AgingCalculator;
use App\Sales\Report\CustomerStatement\StatementAssembler;
use PHPUnit\Framework\TestCase;

final class StatementAssemblerTest extends TestCase
{
    private StatementAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new StatementAssembler();
    }

    public function testOpeningBalanceNetsCargosAndAbonosBeforeTheRange(): void
    {
        $statement = $this->assembler->assemble(
            1,
            'ACME',
            '20123456789',
            '2026-02-01',
            '2026-02-28',
            charges: [
                $this->charge('s1', '2026-01-10', 'F001-1', '500.00', '0.00'),
                $this->charge('s2', '2026-02-15', 'F001-9', '200.00', '200.00'),
            ],
            payments: [
                ['date' => '2026-01-20', 'document' => 'F001-1', 'amount' => '300.00', 'detail' => 'Yape'],
            ],
        );

        // 500 charged - 300 paid, both before 2026-02-01
        self::assertSame('200.00', $statement->openingBalance);
        self::assertSame('200.00', $statement->totalCharges);
        self::assertSame('0.00', $statement->totalPayments);
        self::assertSame('400.00', $statement->closingBalance);
    }

    public function testRunningBalanceWalksEventsChronologicallyChargeBeforePaymentSameDay(): void
    {
        $statement = $this->assembler->assemble(
            1,
            'ACME',
            '',
            '2026-03-01',
            '2026-03-31',
            charges: [
                $this->charge('a', '2026-03-05', 'F-1', '100.00', '0.00'),
                $this->charge('b', '2026-03-10', 'F-2', '80.00', '30.00'),
            ],
            payments: [
                ['date' => '2026-03-10', 'document' => 'F-1', 'amount' => '100.00', 'detail' => ''],
                ['date' => '2026-03-10', 'document' => 'F-2', 'amount' => '50.00', 'detail' => 'Efectivo'],
            ],
        );

        $balances = array_map(static fn($l) => [$l->type, $l->balance], $statement->lines);
        self::assertSame(
            [
                ['Cargo', '100.00'], // +100
                ['Cargo', '180.00'], // +80  (charge before payments on 03-10)
                ['Abono', '80.00'], // -100
                ['Abono', '30.00'], // -50
            ],
            $balances,
        );
        self::assertSame('30.00', $statement->closingBalance);
    }

    public function testChargeReversalAppearsAsAbonoAndCancelsVoidedCreditSale(): void
    {
        $statement = $this->assembler->assemble(
            1,
            'ACME',
            '',
            '2026-05-01',
            '2026-05-31',
            charges: [$this->charge('voided-sale', '2026-05-10', 'B001-7', '100.00', '0.00')],
            payments: [[
                'date' => '2026-05-12',
                'document' => 'B001-7',
                'amount' => '100.00',
                'detail' => 'Anulación de venta',
            ]],
        );

        self::assertSame('100.00', $statement->totalCharges);
        self::assertSame('100.00', $statement->totalPayments);
        self::assertSame('0.00', $statement->closingBalance);
        self::assertSame('Abono', $statement->lines[1]->type);
        self::assertSame('Anulación de venta', $statement->lines[1]->detail);
        self::assertSame('0.00', $statement->lines[1]->balance);
    }

    public function testAgingBucketsOutstandingBalanceByDaysOpenAtCutoff(): void
    {
        $statement = $this->assembler->assemble(
            1,
            'ACME',
            '',
            '2000-01-01',
            '2026-04-30',
            charges: [
                $this->charge('cur', '2026-04-20', 'F-1', '100.00', '100.00'), // 10 days
                $this->charge('m1', '2026-03-20', 'F-2', '200.00', '200.00'), // 41 days
                $this->charge('m2', '2026-02-20', 'F-3', '300.00', '150.00'), // 69 days
                $this->charge('old', '2025-11-01', 'F-4', '400.00', '400.00'), // > 90 days
                $this->charge('paid', '2026-01-01', 'F-5', '50.00', '0.00'), // settled -> excluded
            ],
            payments: [],
        );

        self::assertSame('100.00', $statement->aging[AgingCalculator::BUCKET_CURRENT]);
        self::assertSame('200.00', $statement->aging[AgingCalculator::BUCKET_31_60]);
        self::assertSame('150.00', $statement->aging[AgingCalculator::BUCKET_61_90]);
        self::assertSame('400.00', $statement->aging[AgingCalculator::BUCKET_90_PLUS]);
    }

    public function testEmptyPeriodStillReportsOpeningAndZeroTotals(): void
    {
        $statement = $this->assembler->assemble(
            9,
            'Sin movimientos',
            '',
            '2026-05-01',
            '2026-05-31',
            charges: [$this->charge('x', '2026-01-01', 'F-1', '120.00', '120.00')],
            payments: [],
        );

        self::assertSame([], $statement->lines);
        self::assertSame('120.00', $statement->openingBalance);
        self::assertSame('120.00', $statement->closingBalance);
        self::assertSame('0.00', $statement->totalCharges);
    }

    /**
     * @return array{sale_id: string, date: string, document: string, amount: string, balance_due: string}
     */
    private function charge(string $id, string $date, string $doc, string $amount, string $balanceDue): array
    {
        return [
            'sale_id' => $id,
            'date' => $date,
            'document' => $doc,
            'amount' => $amount,
            'balance_due' => $balanceDue,
        ];
    }
}
