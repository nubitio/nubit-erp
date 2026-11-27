<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Efact\Domain\Finance\Allocation;
use App\Efact\Domain\Finance\OpenItem;
use App\Efact\Domain\Money;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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

    /**
     * The collection row, locked until the transaction ends; null when there is none.
     *
     * @return array<string, mixed>|null
     */
    public function lockCollection(string $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM sales_collection WHERE id = ? FOR UPDATE', [$id]);

        return $row === false ? null : $row;
    }

    /** @return list<array{id: string, installmentId: string, saleId: string, amount: string}> */
    public function activeAllocations(string $collectionId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT a.id, a.installment_id, r.sale_id, a.amount
             FROM sales_collection_allocation a
             JOIN sales_receivable_installment i ON i.id = a.installment_id
             JOIN sales_receivable r ON r.id = i.receivable_id
             WHERE a.collection_id = ? AND a.status = ?
             ORDER BY a.id', [$collectionId, 'active']);

        return array_map(static fn(array $r): array => [
            'id' => (string) $r['id'],
            'installmentId' => (string) $r['installment_id'],
            'saleId' => (string) $r['sale_id'],
            'amount' => (string) $r['amount'],
        ], $rows);
    }

    /** @return list<array{id: string, paymentMethodId: int, amount: string}> */
    public function tenders(string $collectionId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, payment_method_id, amount FROM sales_collection_tender WHERE collection_id = ? ORDER BY id', [
            $collectionId,
        ]);

        return array_map(static fn(array $r): array => [
            'id' => (string) $r['id'],
            'paymentMethodId' => (int) $r['payment_method_id'],
            'amount' => (string) $r['amount'],
        ], $rows);
    }

    /**
     * What the collection booked on the legacy side: one application per sale_payments row it created.
     *
     * @return list<array{saleId: string, salePaymentId: int, paymentMethodId: int, amount: string}>
     */
    public function legacyApplications(string $collectionId): array
    {
        $rows = $this->connection->fetchAllAssociative("SELECT a.sale_id, a.sale_payment_id, p.payment_method_id, a.applied_amount
             FROM customer_payment_applications a
             JOIN customer_account_entries e ON e.id = a.customer_account_entry_id
             JOIN sale_payments p ON p.id = a.sale_payment_id
             WHERE e.source = 'collection' AND e.source_id = ?
             ORDER BY a.id", [$collectionId]);

        return array_map(static fn(array $r): array => [
            'saleId' => (string) $r['sale_id'],
            'salePaymentId' => (int) $r['sale_payment_id'],
            'paymentMethodId' => (int) $r['payment_method_id'],
            'amount' => (string) $r['applied_amount'],
        ], $rows);
    }

    /**
     * Whether any of these sales has credit notes applied to it; a reversal would double count the excess.
     *
     * @param list<string> $saleIds
     */
    public function saleHasCreditNote(array $saleIds): bool
    {
        if ($saleIds === []) {
            return false;
        }

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM customer_account_entries WHERE entry_type = 'credit_note' AND sale_id IN (?)",
            [$saleIds],
            [ArrayParameterType::STRING],
        ) > 0;
    }

    /** Marks an allocation reversed and gives its amount back to the instalment. */
    public function reverseAllocation(string $id, string $installmentId, string $amount, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement('UPDATE sales_collection_allocation SET status = ?, reversed_at = ? WHERE id = ? AND status = ?', [
            'reversed',
            $at->format('Y-m-d H:i:s'),
            $id,
            'active',
        ]);
        $this->connection->executeStatement('UPDATE sales_receivable_installment SET settled_amount = settled_amount - ? WHERE id = ?', [
            $amount,
            $installmentId,
        ]);
    }

    public function markReversed(string $id, DateTimeImmutable $at, ?int $by): void
    {
        $this->connection->executeStatement('UPDATE sales_collection SET status = ?, reversed_at = ?, reversed_by = ? WHERE id = ? AND status = ?', [
            'reversed',
            $at->format('Y-m-d H:i:s'),
            $by,
            $id,
            'confirmed',
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
            throw new ConflictHttpException('Esta clave de idempotencia ya se usó en otra cobranza.');
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
