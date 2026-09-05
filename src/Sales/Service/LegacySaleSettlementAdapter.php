<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Domain\SaleSettlementType;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;

/** Converts the former payment payload into the canonical settlement plan. */
final readonly class LegacySaleSettlementAdapter
{
    public function __construct(
        private SalePaymentPlanService $paymentPlanService = new SalePaymentPlanService(),
    ) {}

    /**
     * @return array<int, array{
     *     type:string,
     *     payment_method_id:?int,
     *     amount:string,
     *     currency:string,
     *     tendered_amount:?string,
     *     change_amount:?string
     * }>
     */
    public function adapt(SaleRecord $sale, string $currency, string $grandTotal): array
    {
        $payments = $this->paymentPlanService->resolveForWrite($sale, $currency, $grandTotal, $sale->isCreditSale());
        $resolved = [];
        $allocated = BigDecimal::zero();
        $receivable = BigDecimal::zero();

        foreach ($payments as $payment) {
            $allocated = $allocated->plus(Money::of($payment['amount']));
            $isReceivable = $payment['payment_method_id'] === PaymentMethod::CREDIT;
            if ($isReceivable) {
                $receivable = $receivable->plus(Money::of($payment['amount']));
                continue;
            }

            $resolved[] = [
                'type' => SaleSettlementType::PAYMENT->value,
                'payment_method_id' => $payment['payment_method_id'],
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
                'tendered_amount' => $payment['tendered_amount'],
                'change_amount' => $payment['change_amount'],
            ];
        }

        $unallocated = Money::money(Money::money($grandTotal)->minus(Money::money($allocated)));
        if ($unallocated->isGreaterThan(BigDecimal::zero())) {
            $receivable = $receivable->plus($unallocated);
        }

        $receivable = Money::money($receivable);
        if ($receivable->isGreaterThan(BigDecimal::zero())) {
            $resolved[] = [
                'type' => SaleSettlementType::RECEIVABLE->value,
                'payment_method_id' => null,
                'amount' => Money::toString($receivable),
                'currency' => $currency,
                'tendered_amount' => null,
                'change_amount' => null,
            ];
        }

        return $resolved;
    }
}
