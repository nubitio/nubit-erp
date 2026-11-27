<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Money;
use App\Sales\Entity\SaleRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

/**
 * Opens the receivable of a credit sale: one row for the debt and one instalment per due date.
 *
 * Today a sale carries a single due date, so it opens with one instalment for what is still pending
 * (`balance_due` when the sale is confirmed, i.e. net of any initial payment: the "monto neto pendiente"
 * SUNAT asks for). A schedule of several instalments plugs in here later without touching callers.
 *
 * Idempotent per sale: the unique index on `sale_id` makes a re-delivered event or a backfill re-run a no-op.
 */
readonly class ReceivableOpener
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** True when a receivable was opened, false when there was nothing to open or it already existed. */
    public function open(SaleRecord $sale, string $origin = 'live'): bool
    {
        $customerId = $sale->getCustomer()?->getId();
        $saleId = $sale->getId();
        $pending = Money::money($sale->getBalanceDue());
        if ($customerId === null || $saleId === null || $sale->getStatus() === 'VOIDED' || !$pending->isPositive()) {
            return false;
        }

        $issued = $sale->getIssuedDate() ?? new DateTimeImmutable('today');
        $due = $sale->getDueDate() ?? $issued;
        $receivableId = Uuid::v7()->toRfc4122();

        try {
            $this->connection->transactional(function () use (
                $receivableId,
                $saleId,
                $customerId,
                $sale,
                $pending,
                $issued,
                $due,
                $origin,
            ): void {
                $this->connection->insert('sales_receivable', [
                    'id' => $receivableId,
                    'sale_id' => $saleId,
                    'customer_id' => (int) $customerId,
                    'branch_id' => $sale->getBranch()?->getId(),
                    'currency' => $sale->getCurrency() !== '' ? $sale->getCurrency() : 'PEN',
                    'total' => Money::toString(Money::money($sale->getGrandTotal())),
                    'net_pending' => Money::toString($pending),
                    'opened_on' => $issued->format('Y-m-d'),
                    'origin' => $origin,
                    'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
                ]);
                $this->connection->insert('sales_receivable_installment', [
                    'id' => Uuid::v7()->toRfc4122(),
                    'receivable_id' => $receivableId,
                    'seq' => 1,
                    'due_date' => $due->format('Y-m-d'),
                    'amount' => Money::toString($pending),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
