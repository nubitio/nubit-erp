<?php

declare(strict_types=1);

namespace App\Sales\MessageHandler;

use App\Sales\Event\SaleConfirmed;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Epic #367 / #381: when a credit sale is confirmed, append the matching CHARGE
 * to the customer's current account (`customer_account_entries`).
 *
 * Reacts to the Sales-owned {@see SaleConfirmed} rather than reaching into
 * SaleProcessor, so the ledger write — and its flush — stays off the sale hot
 * path. Same shape as Pharmacy's RecordBatchDrawdownOnSaleConfirmed.
 *
 * Only sales that hit the customer's account (`receivable_account = 1`, the same
 * population the statement in #366 reads) with a real customer produce a row.
 * Idempotent per sale: a re-delivered event is a no-op.
 */
#[AsMessageHandler]
final readonly class RecordCustomerChargeOnSaleConfirmed
{
    public function __construct(
        private SaleRecordRepository $sales,
        private CustomerAccountEntryRepository $entries,
        private CustomerAccountLedger $ledger,
    ) {}

    public function __invoke(SaleConfirmed $event): void
    {
        if ($this->entries->hasChargeForSale($event->saleId)) {
            return;
        }

        $sale = $this->sales->findByAnyId($event->saleId);
        if ($sale === null || !$sale->isReceivableAccount() || $sale->getStatus() === 'VOIDED') {
            return;
        }

        $customerId = $sale->getCustomer()?->getId();
        if ($customerId === null) {
            return;
        }

        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';
        $document = $serie !== '' && $numero !== '' ? sprintf('%s-%s', $serie, $numero) : null;

        $this->ledger->recordCharge(
            customerId: (int) $customerId,
            saleId: $event->saleId,
            occurredOn: $sale->getIssuedDate() ?? new DateTimeImmutable('today'),
            amount: $sale->getGrandTotal(),
            currency: $sale->getCurrency() !== '' ? $sale->getCurrency() : 'PEN',
            description: $document,
        );
    }
}
