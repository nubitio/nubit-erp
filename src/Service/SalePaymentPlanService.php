<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Core\Exception\ServiceException;
use App\Core\Util\Money;
use App\Entity\SaleRecord;
use Brick\Math\BigDecimal;

/**
 * Resolves and validates payment lines for Sales V2 (single and mixed payments).
 */
final class SalePaymentPlanService
{
    /**
     * @return array<int, array{
     *     payment_method_id:int,
     *     amount:string,
     *     currency:string,
     *     tendered_amount:?string,
     *     change_amount:?string
     * }>
     *
     * @throws ServiceException
     */
    public function resolveForWrite(SaleRecord $sale, string $currency, string $grandTotal): array
    {
        $total = Money::money($grandTotal);
        $payments = $sale->getPayments();
        if ($payments === []) {
            $methodId = $sale->getTipoPago()?->getId();
            if ($methodId === null) {
                throw new ServiceException('Debe indicar al menos un método de pago.');
            }

            return [[
                'payment_method_id' => $methodId,
                'amount' => Money::toString($total),
                'currency' => $currency,
                'tendered_amount' => null,
                'change_amount' => null,
            ]];
        }

        $resolved = [];
        $sum = BigDecimal::zero();
        foreach ($payments as $index => $payment) {
            $methodId = $this->resolvePaymentMethodId($payment);
            if ($methodId === null) {
                throw new ServiceException(sprintf('payment_method_id inválido en posición %d.', $index));
            }

            $amount = Money::money($payment['amount'] ?? 0);
            if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw new ServiceException(sprintf('El monto del pago en posición %d debe ser mayor a 0.', $index));
            }
            $sum = $sum->plus($amount);

            $tendered = $payment['tendered_amount'] ?? $payment['amountReceived'] ?? null;
            $change = $payment['change_amount'] ?? $payment['changeAmount'] ?? null;

            $resolved[] = [
                'payment_method_id' => $methodId,
                'amount' => Money::toString($amount),
                'currency' => $currency,
                'tendered_amount' => $tendered !== null ? Money::toString(Money::money($tendered)) : null,
                'change_amount' => $change !== null ? Money::toString(Money::money($change)) : null,
            ];
        }

        $sumMoney = Money::money($sum);
        $diff = Money::money($total->minus($sumMoney));
        if (!$diff->abs()->isLessThanOrEqualTo(Money::money('0.01'))) {
            throw new ServiceException(sprintf(
                'La suma de pagos (%s) no coincide con el total de la venta (%s).',
                Money::toString($sumMoney),
                Money::toString($total)
            ));
        }

        return $resolved;
    }

    /**
     * @throws ServiceException
     */
    public function resolveCashCollectedAmount(SaleRecord $sale, string $grandTotal): string
    {
        $currency = strtoupper(trim($sale->getCurrency()));
        $payments = $this->resolveForWrite($sale, $currency !== '' ? $currency : 'PEN', $grandTotal);

        $cash = BigDecimal::zero();
        foreach ($payments as $payment) {
            if ($payment['payment_method_id'] === PaymentMethod::CASH) {
                $cash = $cash->plus(Money::of($payment['amount']));
            }
        }

        return Money::toString(Money::money($cash));
    }

    /**
     * @param array<string, mixed> $payment
     */
    private function resolvePaymentMethodId(array $payment): ?int
    {
        $raw = $payment['payment_method_id']
            ?? $payment['paymentMethodId']
            ?? $payment['methodId']
            ?? null;

        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return null;
            }

            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }

            if (preg_match('/(\\d+)$/', $trimmed, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
