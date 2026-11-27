<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Finance\OpenItem;
use App\Efact\Domain\Money;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The open instalments of a customer's open sales, locked. Every sale must have its receivable (one is opened
 * now for a sale that predates the module) and its instalments must still add up to what the sale owes;
 * otherwise the two worlds disagree and nothing is booked.
 */
readonly class OpenInstallmentLoader
{
    public function __construct(
        private CollectionStore $store,
        private ReceivableOpener $opener,
        private InstallmentReconciler $reconciler,
    ) {}

    /**
     * @param array<string, SaleRecord> $bySale
     *
     * @return list<array{item: OpenItem, saleId: string}>
     */
    public function load(array $bySale): array
    {
        $have = $this->store->saleIdsWithReceivable(array_keys($bySale));
        foreach ($bySale as $saleId => $sale) {
            if (!in_array($saleId, $have, strict: true)) {
                $this->opener->open($sale, 'backfill');
            }
        }

        $items = $this->store->lockOpenInstallments(array_keys($bySale));
        if ($this->reconcileDrift($bySale, $items)) {
            $items = $this->store->lockOpenInstallments(array_keys($bySale));
        }
        $open = $this->openBySale($items);
        foreach ($bySale as $saleId => $sale) {
            if (!($open[$saleId] ?? BigDecimal::zero())->isEqualTo(Money::money($sale->getBalanceDue()))) {
                throw new ConflictHttpException(sprintf(
                    'El saldo de %s no coincide con sus cuotas. Ejecute finance:audit antes de cobrar.',
                    $saleId,
                ));
            }
        }

        return $items;
    }

    /**
     * @param array<string, SaleRecord> $bySale
     * @param list<array{item: OpenItem, saleId: string}> $items
     */
    private function reconcileDrift(array $bySale, array $items): bool
    {
        $changed = false;
        foreach ($bySale as $saleId => $sale) {
            $mine = array_column(
                array_filter($items, static fn(array $row): bool => $row['saleId'] === $saleId),
                'item',
            );
            $changed = $this->reconciler->reconcile($sale, $mine) || $changed;
        }

        return $changed;
    }

    /**
     * @param list<array{item: OpenItem, saleId: string}> $items
     *
     * @return array<string, BigDecimal> open amount per sale
     */
    private function openBySale(array $items): array
    {
        $open = [];
        foreach ($items as $row) {
            $open[$row['saleId']] = ($open[$row['saleId']] ?? BigDecimal::zero())->plus($row['item']->open);
        }

        return $open;
    }
}
