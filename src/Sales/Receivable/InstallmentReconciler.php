<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Finance\OpenItem;
use App\Efact\Domain\Money;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use DateTimeImmutable;

/**
 * Keeps a receivable's instalments equal to what its sale owes when the old flows (a payment from the sale detail,
 * an integration collection, a credit note) changed the sale without knowing about them.
 *
 * The sale's `balance_due` is the authority while both worlds coexist. The difference is written down, not hidden:
 * an adjustment of kind `legacy_sync` records how much and when it was noticed, and the instalments absorb it
 * oldest due date first. Called under the locks of the collection that needs the instalments.
 */
readonly class InstallmentReconciler
{
    public const string KIND = 'legacy_sync';

    public function __construct(
        private CollectionStore $store,
    ) {}

    /**
     * @param list<OpenItem> $open the sale's open instalments, locked, oldest due date first
     *
     * @return bool whether anything changed (the caller must reload the instalments)
     */
    public function reconcile(SaleRecord $sale, array $open): bool
    {
        $owed = Money::money($sale->getBalanceDue());
        $inInstalments = array_reduce(
            $open,
            static fn(BigDecimal $carry, OpenItem $i): BigDecimal => $carry->plus($i->open),
            BigDecimal::zero(),
        );
        $difference = $inInstalments->minus($owed);
        $saleId = (string) $sale->getId();
        $receivableId = $this->store->receivableIdOf($saleId);
        if ($difference->isZero() || $receivableId === null) {
            return false;
        }

        if ($difference->isPositive()) {
            $this->absorb($receivableId, $open, $difference);

            return true;
        }

        $due = ($sale->getDueDate() ?? $sale->getIssuedDate() ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $this->store->addInstallment($saleId, $due, Money::toString($difference->negated()));
        $this->store->insertAdjustment(
            $receivableId,
            self::KIND,
            Money::toString($difference->negated()),
            'El saldo de la venta subió por un flujo anterior; se agregó una cuota.',
        );

        return true;
    }

    /**
     * @param list<OpenItem> $open
     */
    private function absorb(string $receivableId, array $open, BigDecimal $amount): void
    {
        $left = $amount;
        foreach ($open as $item) {
            if (!$left->isPositive()) {
                break;
            }
            $take = BigDecimal::min($left, $item->open);
            $this->store->adjustInstallment($item->id, Money::toString($take));
            $left = $left->minus($take);
        }
        $this->store->insertAdjustment(
            $receivableId,
            self::KIND,
            Money::toString($amount),
            'Cobro o nota de crédito registrado por un flujo anterior.',
        );
    }
}
