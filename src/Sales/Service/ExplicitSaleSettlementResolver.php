<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Efact\Domain\Money;
use App\Sales\Domain\SaleSettlementType;
use Brick\Math\BigDecimal;
use Nubit\Platform\Exception\ServiceException;

final readonly class ExplicitSaleSettlementResolver
{
    public function __construct(
        private SettlementPaymentMethodResolver $paymentMethodResolver = new SettlementPaymentMethodResolver(),
    ) {}

    /**
     * @param array<int, array<string, mixed>> $settlements
     *
     * @return array<int, array{
     *     type:string,
     *     payment_method_id:?int,
     *     amount:string,
     *     currency:string,
     *     tendered_amount:?string,
     *     change_amount:?string
     * }>
     */
    public function resolve(array $settlements, string $currency, string $grandTotal): array
    {
        $resolved = [];
        foreach ($settlements as $index => $settlement) {
            $resolved[] = $this->resolveLine($settlement, $currency, $index);
        }

        $receivables = array_filter(
            $resolved,
            static fn(array $line): bool => $line['type'] === SaleSettlementType::RECEIVABLE->value,
        );
        if (count($receivables) > 1) {
            throw new ServiceException('Solo se permite una asignación a cuenta por cobrar por venta.');
        }

        $this->assertMatchesTotal($resolved, $grandTotal);

        return $resolved;
    }

    /**
     * @param array<string, mixed> $settlement
     *
     * @return array{
     *     type:string,
     *     payment_method_id:?int,
     *     amount:string,
     *     currency:string,
     *     tendered_amount:?string,
     *     change_amount:?string
     * }
     */
    private function resolveLine(array $settlement, string $currency, int $index): array
    {
        $type = SaleSettlementType::tryFrom(strtoupper(trim((string) ($settlement['type'] ?? ''))));
        if ($type === null) {
            throw new ServiceException(sprintf('Tipo de liquidación inválido en posición %d.', $index));
        }

        $amount = Money::money($settlement['amount'] ?? 0);
        if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new ServiceException(sprintf(
                'El monto de la liquidación en posición %d debe ser mayor a 0.',
                $index,
            ));
        }

        $methodId = $this->resolveMethodId($type, $settlement, $index);
        $tendered = $settlement['tendered_amount'] ?? $settlement['amountReceived'] ?? null;
        $change = $settlement['change_amount'] ?? $settlement['changeAmount'] ?? null;

        return [
            'type' => $type->value,
            'payment_method_id' => $methodId,
            'amount' => Money::toString($amount),
            'currency' => $currency,
            'tendered_amount' =>
                $type === SaleSettlementType::PAYMENT && $tendered !== null
                    ? Money::toString(Money::money($tendered))
                    : null,
            'change_amount' =>
                $type === SaleSettlementType::PAYMENT && $change !== null
                    ? Money::toString(Money::money($change))
                    : null,
        ];
    }

    /**
     * @param array<string, mixed> $settlement
     */
    private function resolveMethodId(SaleSettlementType $type, array $settlement, int $index): ?int
    {
        $methodId = $this->paymentMethodResolver->id($settlement);
        if ($type === SaleSettlementType::PAYMENT) {
            return $this->paymentMethodResolver->requireCollected($methodId, $index);
        }
        if ($methodId !== null) {
            throw new ServiceException('Una cuenta por cobrar no debe incluir método de pago.');
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $settlements
     */
    private function assertMatchesTotal(array $settlements, string $grandTotal): void
    {
        $sum = BigDecimal::zero();
        foreach ($settlements as $settlement) {
            $sum = $sum->plus(Money::of($settlement['amount']));
        }

        $total = Money::money($grandTotal);
        $diff = Money::money($total->minus(Money::money($sum)));
        if (!$diff->abs()->isLessThanOrEqualTo(Money::money('0.01'))) {
            throw new ServiceException(sprintf(
                'La suma de liquidaciones (%s) no coincide con el total de la venta (%s).',
                Money::toString(Money::money($sum)),
                Money::toString($total),
            ));
        }
    }
}
