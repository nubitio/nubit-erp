<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Checks on a {@see CollectOnAccountPlanner} plan before anything is booked.
 *
 * @phpstan-import-type PlanRow from CollectOnAccountPlanner
 */
final readonly class CollectionPlanGuard
{
    /** A collection needs an active method that stands for money actually received. */
    public static function assertCollectableMethod(PaymentMethod $paymentMethod): void
    {
        if (
            !$paymentMethod->getIsActive()
            || $paymentMethod->getSettlementBehavior() !== SettlementBehavior::COLLECTED
        ) {
            throw new UnprocessableEntityHttpException(
                'Seleccione un método de pago activo que represente dinero recibido. Crédito no es un cobro.',
            );
        }
    }

    /** @param list<PlanRow> $plan */
    public static function assertCovers(array $plan, BigDecimal $amount, string $excessHint = ''): void
    {
        $applied = array_reduce(
            $plan,
            static fn(BigDecimal $carry, array $row): BigDecimal => $carry->plus($row['amount']),
            BigDecimal::zero(),
        );
        if ($applied->isLessThan($amount)) {
            throw new UnprocessableEntityHttpException(trim(sprintf(
                'El monto (%s) excede la deuda que se puede aplicar (%s). Ajuste el monto o las facturas. %s',
                Money::toString($amount),
                Money::toString($applied),
                $excessHint,
            )));
        }
    }

    /**
     * The currency a plan is booked in: that of its first sale ({@see assertSingleCurrency()} guarantees all match).
     *
     * @param list<PlanRow> $plan
     */
    public static function currencyOf(array $plan): string
    {
        $currency = $plan === [] ? '' : $plan[0]['sale']->getCurrency();

        return $currency !== '' ? $currency : 'PEN';
    }

    /**
     * A collection is booked in one currency (D-04): reject a plan that mixes PEN and USD sales
     * instead of silently booking everything in the first sale's currency (G-15).
     *
     * @param list<PlanRow> $plan
     */
    public static function assertSingleCurrency(array $plan): void
    {
        $currencies = [];
        foreach ($plan as $row) {
            $currencies[$row['sale']->getCurrency() !== '' ? $row['sale']->getCurrency() : 'PEN'] = true;
        }
        if (count($currencies) > 1) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Un cobro se registra en una sola moneda; las facturas seleccionadas están en %s. Cobre cada moneda por separado.',
                implode(' y ', array_keys($currencies)),
            ));
        }
    }

}
