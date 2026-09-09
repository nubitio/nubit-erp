<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;

use function usort;

/**
 * Pure assembly of a {@see CustomerStatement} from already-fetched rows. No I/O,
 * so the running-balance and period-total maths are unit-testable in isolation.
 * Aging lives in {@see AgingCalculator}.
 *
 * @phpstan-type ChargeRow array{sale_id: string, date: string, document: string, amount: string, balance_due: string}
 * @phpstan-type PaymentRow array{date: string, document: string, amount: string, detail: string}
 * @phpstan-type Movement array{sort: string, kind: string, date: string, document: string, detail: string, amount: string}
 */
final readonly class StatementAssembler
{
    public function __construct(
        private AgingCalculator $aging = new AgingCalculator(),
    ) {}

    /**
     * @param list<ChargeRow>  $charges  every non-voided credit sale for the customer with date <= $dateTo
     * @param list<PaymentRow> $payments every abono against those sales with date <= $dateTo
     */
    public function assemble(
        int $customerId,
        string $customerName,
        string $customerDoc,
        string $dateFrom,
        string $dateTo,
        array $charges,
        array $payments,
    ): CustomerStatement {
        $opening = $this->openingBalance($charges, $payments, $dateFrom);

        $balance = $opening;
        $totalCharges = BigDecimal::zero();
        $totalPayments = BigDecimal::zero();
        $lines = [];
        foreach ($this->movementsInRange($charges, $payments, $dateFrom, $dateTo) as $movement) {
            $amount = Money::of($movement['amount']);
            $isCharge = $movement['kind'] === 'charge';
            $balance = $isCharge ? $balance->plus($amount) : $balance->minus($amount);
            $totalCharges = $isCharge ? $totalCharges->plus($amount) : $totalCharges;
            $totalPayments = $isCharge ? $totalPayments : $totalPayments->plus($amount);

            $lines[] = new StatementLine(
                $movement['date'],
                $isCharge ? 'Cargo' : 'Abono',
                $movement['document'],
                $movement['detail'],
                $isCharge ? Money::toString($amount) : '0.00',
                $isCharge ? '0.00' : Money::toString($amount),
                Money::toString($balance),
            );
        }

        return new CustomerStatement(
            $customerId,
            $customerName,
            $customerDoc,
            $dateFrom,
            $dateTo,
            Money::toString($opening),
            $lines,
            Money::toString($totalCharges),
            Money::toString($totalPayments),
            Money::toString($opening->plus($totalCharges)->minus($totalPayments)),
            $this->aging->bucketed($charges, $dateTo),
        );
    }

    /**
     * Net of every charge and abono strictly before the range start.
     *
     * @param list<ChargeRow>  $charges
     * @param list<PaymentRow> $payments
     */
    private function openingBalance(array $charges, array $payments, string $dateFrom): BigDecimal
    {
        $opening = BigDecimal::zero();
        foreach ($charges as $charge) {
            $opening = $charge['date'] < $dateFrom ? $opening->plus(Money::of($charge['amount'])) : $opening;
        }

        foreach ($payments as $payment) {
            $opening = $payment['date'] < $dateFrom ? $opening->minus(Money::of($payment['amount'])) : $opening;
        }

        return $opening;
    }

    /**
     * Charges and abonos inside [dateFrom, dateTo], ordered by date with the
     * charge before its abonos on the same day.
     *
     * @param list<ChargeRow>  $charges
     * @param list<PaymentRow> $payments
     *
     * @return list<Movement>
     */
    private function movementsInRange(array $charges, array $payments, string $dateFrom, string $dateTo): array
    {
        $movements = [];
        foreach ($charges as $charge) {
            if ($charge['date'] >= $dateFrom && $charge['date'] <= $dateTo) {
                $movements[] = [
                    'sort' => $charge['date'] . '-0',
                    'kind' => 'charge',
                    'date' => $charge['date'],
                    'document' => $charge['document'],
                    'detail' => 'Venta al crédito',
                    'amount' => $charge['amount'],
                ];
            }
        }

        foreach ($payments as $payment) {
            if ($payment['date'] >= $dateFrom && $payment['date'] <= $dateTo) {
                $movements[] = [
                    'sort' => $payment['date'] . '-1',
                    'kind' => 'payment',
                    'date' => $payment['date'],
                    'document' => $payment['document'],
                    'detail' => $payment['detail'] === '' ? 'Cobro' : $payment['detail'],
                    'amount' => $payment['amount'],
                ];
            }
        }

        usort($movements, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $movements;
    }
}
