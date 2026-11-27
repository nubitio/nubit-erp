<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Finance\Allocation;
use App\Efact\Domain\Finance\OpenItem;
use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The only writer and reader of the collection, tender, allocation and instalment tables. Rows are inserted and
 * instalment totals move forward; nothing here deletes (ADR-003).
 */
readonly class CollectionStore
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * The open instalments of these sales, locked until the surrounding transaction ends.
     *
     * @param list<string> $saleIds
     *
     * @return list<array{item: OpenItem, saleId: string}>
     */
    public function lockOpenInstallments(array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT i.id, i.due_date, r.opened_on, r.sale_id, i.amount - i.settled_amount - i.adjusted_amount AS open_amount
             FROM sales_receivable_installment i
             JOIN sales_receivable r ON r.id = i.receivable_id
             WHERE r.sale_id IN (?) AND i.amount - i.settled_amount - i.adjusted_amount > 0
             ORDER BY i.due_date, i.id
             FOR UPDATE',
            [$saleIds],
            [ArrayParameterType::STRING],
        );

        return array_map(static fn(array $row): array => [
            'item' => new OpenItem(
                (string) $row['id'],
                new DateTimeImmutable((string) $row['due_date']),
                new DateTimeImmutable((string) $row['opened_on']),
                Money::money((string) $row['open_amount']),
            ),
            'saleId' => (string) $row['sale_id'],
        ], $rows);
    }

    /**
     * @param list<string> $saleIds
     *
     * @return list<string> the ones that already have a receivable
     */
    public function saleIdsWithReceivable(array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT sale_id FROM sales_receivable WHERE sale_id IN (?)',
            [$saleIds],
            [ArrayParameterType::STRING],
        );
    }

    /**
     * Writes the collection, its tenders and its allocations.
     *
     * @param list<Allocation> $allocations
     *
     * @return list<string> the tender ids, in the order of the request
     */
    public function record(
        string $collectionId,
        CollectionRequest $request,
        ?string $branchId,
        string $total,
        string $unapplied,
        array $allocations,
    ): array {
        $this->insertCollection($collectionId, $request, $branchId, $total, $unapplied);
        $tenderIds = [];
        foreach ($request->tenders as $tender) {
            $tenderId = Uuid::v7()->toRfc4122();
            $this->insertTender($tenderId, $collectionId, $tender);
            $tenderIds[] = $tenderId;
        }
        foreach ($allocations as $allocation) {
            $this->allocate(
                $collectionId,
                $allocation->itemId,
                Money::toString($allocation->amount),
                $request->receivedOn,
            );
        }

        return $tenderIds;
    }

    public function insertCollection(
        string $id,
        CollectionRequest $request,
        ?string $branchId,
        string $total,
        string $unapplied,
    ): void {
        $this->connection->insert('sales_collection', [
            'id' => $id,
            'customer_id' => $request->customerId,
            'branch_id' => $branchId,
            'currency' => $request->currency,
            'received_on' => $request->receivedOn->format('Y-m-d'),
            'total' => $total,
            'unapplied' => $unapplied,
            'idempotency_key' => $request->idempotencyKey,
            'created_by' => $request->createdBy->getId(),
            'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);
    }

    public function insertTender(string $id, string $collectionId, TenderInput $tender): void
    {
        $this->connection->insert('sales_collection_tender', [
            'id' => $id,
            'collection_id' => $collectionId,
            'payment_method_id' => $tender->method->getId(),
            'money_account_id' => $tender->moneyAccountId,
            'amount' => Money::toString(Money::money($tender->amount)),
            'reference' => $tender->reference,
        ]);
    }

    /** Records the allocation and moves the instalment's settled amount forward by the same amount. */
    public function allocate(string $collectionId, string $installmentId, string $amount, DateTimeImmutable $on): void
    {
        $this->connection->insert('sales_collection_allocation', [
            'id' => Uuid::v7()->toRfc4122(),
            'collection_id' => $collectionId,
            'installment_id' => $installmentId,
            'amount' => $amount,
            'applied_on' => $on->format('Y-m-d'),
        ]);
        $this->connection->executeStatement('UPDATE sales_receivable_installment SET settled_amount = settled_amount + ? WHERE id = ?', [
            $amount,
            $installmentId,
        ]);
    }

    /** The result of a collection already registered under this key, or null. */
    public function resultFor(string $idempotencyKey, int $customerId): ?CollectionResult
    {
        $collection = $this->connection->fetchAssociative('SELECT * FROM sales_collection WHERE idempotency_key = ?', [
            $idempotencyKey,
        ]);
        if ($collection === false) {
            return null;
        }
        if ((int) $collection['customer_id'] !== $customerId) {
            throw new \DomainException('The idempotency key belongs to another customer.');
        }

        $id = (string) $collection['id'];
        $allocations = $this->connection->fetchAllAssociative('SELECT a.installment_id, r.sale_id, a.amount
             FROM sales_collection_allocation a
             JOIN sales_receivable_installment i ON i.id = a.installment_id
             JOIN sales_receivable r ON r.id = i.receivable_id
             WHERE a.collection_id = ? AND a.status = ?
             ORDER BY a.id', [$id, 'active']);
        $tenders = $this->connection->fetchAllAssociative('SELECT id, payment_method_id, amount FROM sales_collection_tender WHERE collection_id = ? ORDER BY id', [
            $id,
        ]);

        return new CollectionResult(
            collectionId: $id,
            customerId: $customerId,
            currency: (string) $collection['currency'],
            total: (string) $collection['total'],
            unapplied: (string) $collection['unapplied'],
            allocations: array_map(static fn(array $a): array => [
                'installmentId' => (string) $a['installment_id'],
                'saleId' => (string) $a['sale_id'],
                'amount' => (string) $a['amount'],
            ], $allocations),
            tenders: array_map(static fn(array $t): array => [
                'tenderId' => (string) $t['id'],
                'paymentMethodId' => (int) $t['payment_method_id'],
                'amount' => (string) $t['amount'],
            ], $tenders),
            replayed: true,
        );
    }
}
