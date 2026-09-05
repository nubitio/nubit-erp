<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Domain\SaleSettlementType;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;

/** Canonical boundary used by sale processing, persistence and cash movement. */
final readonly class SaleSettlementPlanService
{
    public function __construct(
        private ExplicitSaleSettlementResolver $explicitResolver = new ExplicitSaleSettlementResolver(),
        private LegacySaleSettlementAdapter $legacyAdapter = new LegacySaleSettlementAdapter(),
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
    public function resolveForWrite(SaleRecord $sale, string $currency, string $grandTotal): array
    {
        if ($sale->getSettlements() !== []) {
            $resolved = $this->explicitResolver->resolve($sale->getSettlements(), $currency, $grandTotal);
            $this->assertIdentifiedReceivableCustomer($sale, $resolved);

            return $resolved;
        }

        return $this->legacyAdapter->adapt($sale, $currency, $grandTotal);
    }

    public function resolveCashCollectedAmount(SaleRecord $sale, string $grandTotal): string
    {
        $currency = strtoupper(trim($sale->getCurrency()));
        $cash = BigDecimal::zero();

        foreach ($this->resolveForWrite($sale, $currency !== '' ? $currency : 'PEN', $grandTotal) as $settlement) {
            if (
                $settlement['type'] !== SaleSettlementType::PAYMENT->value
                || $settlement['payment_method_id'] !== PaymentMethod::CASH
            ) {
                continue;
            }

            $cash = $cash->plus(Money::of($settlement['amount']));
        }

        return Money::toString(Money::money($cash));
    }

    /**
     * @param array<int, array<string, mixed>> $settlements
     */
    private function assertIdentifiedReceivableCustomer(SaleRecord $sale, array $settlements): void
    {
        $hasReceivable = array_any(
            $settlements,
            static fn(array $line): bool => $line['type'] === SaleSettlementType::RECEIVABLE->value,
        );
        $customer = $sale->getCustomer();
        if ($hasReceivable && ($customer === null || $customer->getRuc() === '00000000')) {
            throw new \Nubit\Platform\Exception\ServiceException(
                'Debe seleccionar un cliente identificado para registrar una cuenta por cobrar.',
            );
        }
    }
}
