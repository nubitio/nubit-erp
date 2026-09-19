<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * Cancels the still-open CHARGE of a voided credit sale on the customer's
 * current account (mirrors {@see CashRefundRegistrar} for the cash side).
 *
 * Only reachable once {@see \App\Sales\Domain\SaleException::hasAppliedPayments()}
 * has cleared in {@see \App\Billing\Sale\SaleVoidUseCase}, so balance_due here
 * always equals the sale's untouched grand_total — nothing was ever collected
 * against it.
 */
final readonly class ReceivableVoidReverser
{
    public function __construct(
        private CustomerAccountEntryRepository $entries,
        private CustomerAccountLedger $ledger,
        private LoggerInterface $logger,
    ) {}

    public function reverse(SaleRecord $sale): void
    {
        if (!$sale->isReceivableAccount()) {
            return;
        }

        $customerId = $sale->getCustomer()?->getId();
        if ($customerId === null) {
            $this->logger->warning('Receivable void reversal skipped — sale has no customer', [
                'sale_id' => $sale->getId(),
            ]);

            return;
        }

        $saleId = $sale->getId();
        if ($saleId === null || !$this->entries->hasChargeForSale($saleId)) {
            return;
        }

        if ($this->entries->hasReversalForSale($saleId)) {
            return;
        }

        $this->ledger->recordChargeReversal(
            customerId: (int) $customerId,
            saleId: $saleId,
            occurredOn: new DateTimeImmutable(),
            amount: $sale->getBalanceDue(),
            currency: $sale->getCurrency() !== '' ? $sale->getCurrency() : 'PEN',
            description: sprintf('Anulación de %s', $this->documentLabel($sale)),
        );
    }

    private function documentLabel(SaleRecord $sale): string
    {
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';

        return $serie !== '' && $numero !== '' ? sprintf('%s-%s', $serie, $numero) : 'venta anulada';
    }
}
