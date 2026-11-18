<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Sales\Entity\SaleRecord;
use App\Treasury\Domain\MovementSource;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Tells Treasury about the non-cash payments of a point-of-sale sale (card, Yape, transfer…). Cash is left out
 * because the sale already writes its drawer movement.
 *
 * Unlike a collection, a sale is never refused because of Treasury: the POS must keep selling, also offline, and
 * a missing account or branch is a configuration problem, not a reason to lose a sale. A failure is logged and
 * the payment stays without a movement until `treasury:backfill` records it; `treasury:verify` reports it.
 *
 * The key is the payment's own idempotency key, not its id: the gateway rebuilds the payment rows on every write,
 * but derives the key from the sale and the line, so a retried or replayed sale records each payment once.
 */
final readonly class SalePaymentsTreasury
{
    public function __construct(
        private TreasuryReceipts $receipts,
        private LoggerInterface $logger,
    ) {}

    public function record(SaleRecord $sale, ?int $userId): void
    {
        $customer = $sale->getCliente();
        foreach ($sale->getSalePayments() as $payment) {
            $method = $payment->getPaymentMethod();
            $key = $payment->getIdempotencyKey();
            if ($method === null || $key === null || $method->getId() === PaymentMethod::CASH) {
                continue;
            }
            if ($method->getSettlementBehavior() !== SettlementBehavior::COLLECTED) {
                continue;
            }

            try {
                $this->receipts->record(new TreasuryReceipt(
                    source: MovementSource::SalePayment,
                    sourceId: $key,
                    amount: $payment->getAmount(),
                    currency: $payment->getCurrency(),
                    paymentMethod: $method,
                    paidAt: DateTimeImmutable::createFromInterface($payment->getPaidAt() ?? new DateTimeImmutable()),
                    userId: $userId,
                    branchId: $sale->getBranch()?->getId(),
                    customerId: $customer === null ? null : (string) $customer->getId(),
                    reference: $payment->getReference(),
                    description: $this->description($sale),
                    moneyAccountId: null,
                ));
            } catch (Throwable $e) {
                $this->logger->error('Treasury could not record a sale payment; run treasury:backfill to repair it.', [
                    'sale_id' => $sale->getId(),
                    'payment_key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function description(SaleRecord $sale): string
    {
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';

        return $serie !== '' && $numero !== '' ? sprintf('Venta %s-%s', $serie, $numero) : 'Venta';
    }
}
