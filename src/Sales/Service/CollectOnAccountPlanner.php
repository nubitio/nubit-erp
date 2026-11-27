<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Efact\Domain\Money;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Turns a "cobro a cuenta" request into a concrete list of (sale, amount) to
 * apply: FIFO over the customer's open sales, or an explicit per-sale split.
 * Pure — no persistence, so {@see CollectOnAccountService} stays small.
 *
 * @phpstan-type PlanRow array{sale: SaleRecord, amount: BigDecimal}
 */
final readonly class CollectOnAccountPlanner
{
    /**
     * The plan for a request: FIFO when no split is given, the explicit split otherwise; checked to cover
     * the whole amount and to stay in one currency.
     *
     * @param list<SaleRecord> $openSales oldest first
     * @param list<array{saleId: string, amount: string|float}> $applications empty = FIFO
     *
     * @return list<PlanRow>
     */
    public function plan(array $openSales, BigDecimal $amount, array $applications, string $excessHint = ''): array
    {
        $plan = $applications === []
            ? $this->fifo($openSales, $amount)
            : $this->explicit($openSales, $applications);
        CollectionPlanGuard::assertCovers($plan, $amount, $excessHint);
        CollectionPlanGuard::assertSingleCurrency($plan);

        return $plan;
    }

    /**
     * @param list<SaleRecord> $openSales oldest first
     *
     * @return list<PlanRow>
     */
    public function fifo(array $openSales, BigDecimal $amount): array
    {
        $remaining = $amount;
        $plan = [];
        foreach ($openSales as $sale) {
            if ($remaining->isLessThanOrEqualTo(0)) {
                break;
            }
            $take = BigDecimal::min($remaining, Money::money($sale->getBalanceDue()));
            if ($take->isLessThanOrEqualTo(0)) {
                continue;
            }
            $plan[] = ['sale' => $sale, 'amount' => $take];
            $remaining = $remaining->minus($take);
        }

        return $plan;
    }

    /**
     * @param list<SaleRecord> $openSales
     * @param list<array{saleId: string, amount: string|float}> $applications
     *
     * @return list<PlanRow>
     */
    public function explicit(array $openSales, array $applications): array
    {
        $byId = [];
        foreach ($openSales as $sale) {
            $byId[(string) $sale->getId()] = $sale;
        }

        $plan = [];
        $seen = [];
        foreach ($applications as $line) {
            $saleId = $line['saleId'];
            if (array_key_exists($saleId, $seen)) {
                throw new UnprocessableEntityHttpException(sprintf('La factura %s está repetida.', $saleId));
            }
            $seen[$saleId] = true;

            $sale = $byId[$saleId] ?? null;
            if ($sale === null) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'La factura %s no está pendiente para este cliente.',
                    $saleId,
                ));
            }

            $lineAmount = Money::money($line['amount']);
            if ($lineAmount->isLessThanOrEqualTo(0)) {
                throw new UnprocessableEntityHttpException('Cada importe aplicado debe ser mayor a 0.');
            }
            if ($lineAmount->isGreaterThan(Money::money($sale->getBalanceDue()))) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'El importe para %s (%s) excede su saldo pendiente (%s).',
                    self::documentLabel($sale),
                    Money::toString($lineAmount),
                    Money::toString(Money::money($sale->getBalanceDue())),
                ));
            }

            $plan[] = ['sale' => $sale, 'amount' => $lineAmount];
        }

        return $plan;
    }

    public static function documentLabel(SaleRecord $sale): string
    {
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';

        return $serie !== '' && $numero !== '' ? sprintf('%s-%s', $serie, $numero) : 'Documento interno';
    }
}
