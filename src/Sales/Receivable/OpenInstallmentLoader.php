<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

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
    ) {}

    /**
     * @param array<string, SaleRecord> $bySale
     *
     * @return list<array{item: \App\Efact\Domain\Finance\OpenItem, saleId: string}>
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
        $open = [];
        foreach ($items as $row) {
            $open[$row['saleId']] = ($open[$row['saleId']] ?? BigDecimal::zero())->plus($row['item']->open);
        }
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
}
