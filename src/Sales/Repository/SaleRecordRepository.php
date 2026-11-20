<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Sales\Entity\SaleRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaleRecord>
 */
class SaleRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleRecord::class);
    }

    /**
     * Find a SaleRecord by sale UUID.
     */
    public function findByAnyId(string $id): ?SaleRecord
    {
        $result = $this->find($id);

        return $result instanceof SaleRecord ? $result : null;
    }

    /**
     * A customer's sales that still owe money, oldest first — the FIFO order a
     * "cobro a cuenta" (#381) applies a payment in. `balance_due > 0` and
     * `receivable_account = 1` match the population the statement (#366) reads.
     *
     * @return list<SaleRecord>
     */
    public function findOpenReceivablesForCustomer(int $customerId): array
    {
        /** @var list<SaleRecord> $rows */
        $rows = $this
            ->createQueryBuilder('s')
            ->where('s.customer = :customer')
            ->andWhere('s.receivableAccount = true')
            ->andWhere('s.balanceDue > 0')
            ->andWhere("s.status <> 'VOIDED'")
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('customer', $customerId)
            ->orderBy('s.issuedDate', 'ASC')
            ->addOrderBy('s.issuedAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Outstanding balance grouped by customer, oldest-due first — the "Deudores"
     * summary. Mirrors the population {@see findOpenReceivablesForCustomer}
     * reads per customer, aggregated across all customers with an open balance.
     *
     * @return list<array{customerId: int, customerName: string, customerPhone: ?string, totalBalance: string, currency: string, earliestDueDate: ?string, pendingCount: int}>
     */
    public function aggregateReceivablesByCustomer(?string $search, int $page, int $perPage): array
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->select('IDENTITY(s.customer) AS customerId')
            ->addSelect('c.businessName AS customerName')
            ->addSelect('c.phone AS customerPhone')
            ->addSelect('SUM(s.balanceDue) AS totalBalance')
            ->addSelect('MAX(s.currency) AS currency')
            ->addSelect('MIN(s.dueDate) AS earliestDueDate')
            ->addSelect('COUNT(s.id) AS pendingCount')
            ->join('s.customer', 'c')
            ->where('s.receivableAccount = true')
            ->andWhere('s.balanceDue > 0')
            ->andWhere("s.status <> 'VOIDED'")
            ->andWhere('s.deletedAt IS NULL')
            ->groupBy('s.customer, c.businessName, c.phone')
            ->orderBy('totalBalance', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $this->applyCustomerSearch($qb, $search);

        /** @var list<array{customerId: int, customerName: string, customerPhone: ?string, totalBalance: string, currency: string, earliestDueDate: ?string, pendingCount: int}> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * Count of distinct customers with an open balance — paired with
     * {@see aggregateReceivablesByCustomer} for pagination.
     */
    public function countReceivableCustomers(?string $search): int
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.customer)')
            ->join('s.customer', 'c')
            ->where('s.receivableAccount = true')
            ->andWhere('s.balanceDue > 0')
            ->andWhere("s.status <> 'VOIDED'")
            ->andWhere('s.deletedAt IS NULL');

        $this->applyCustomerSearch($qb, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Branch-wide totals for the "Deudores" KPI header: overall balance,
     * currency, and how many distinct customers carry it.
     *
     * @return array{total: string, currency: string, debtorCount: int}
     */
    public function sumReceivables(): array
    {
        /** @var array{total: string, currency: string, debtorCount: int} $row */
        $row = $this
            ->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.balanceDue), 0) AS total')
            ->addSelect('COALESCE(MAX(s.currency), \'PEN\') AS currency')
            ->addSelect('COUNT(DISTINCT s.customer) AS debtorCount')
            ->where('s.receivableAccount = true')
            ->andWhere('s.balanceDue > 0')
            ->andWhere("s.status <> 'VOIDED'")
            ->andWhere('s.deletedAt IS NULL')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => $row['total'],
            'currency' => $row['currency'],
            'debtorCount' => (int) $row['debtorCount'],
        ];
    }

    /**
     * Branch-wide balance grouped by how overdue it is, relative to the due
     * date rather than the sale date — this is what a collections screen
     * needs to answer "what should I chase first" (#409). `current` covers
     * both "no due date recorded" and "not yet due"; the rest count full days
     * past the due date, matching {@see AgingCalculator}'s bucket names.
     *
     * Raw SQL (DATEDIFF has no portable DQL equivalent that also survives
     * GROUP BY), so — unlike the QueryBuilder methods above — this bypasses
     * Doctrine's automatic branch filter and scopes by `$branchId` itself.
     *
     * @return array{current: string, '1-30': string, '31-60': string, '61-90': string, '90+': string}
     */
    public function aggregateReceivablesAging(string $branchId): array
    {
        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(<<<'SQL'
                SELECT
                    CASE
                        WHEN due_date IS NULL OR due_date >= CURDATE() THEN 'current'
                        WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30'
                        WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60'
                        WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90'
                        ELSE '90+'
                    END AS bucket,
                    SUM(balance_due) AS total
                FROM sales
                WHERE receivable_account = 1
                  AND balance_due > 0
                  AND status <> 'VOIDED'
                  AND deleted_at IS NULL
                  AND branch_id = ?
                GROUP BY bucket
                SQL, [$branchId]);

        $buckets = ['current' => '0.00', '1-30' => '0.00', '31-60' => '0.00', '61-90' => '0.00', '90+' => '0.00'];
        foreach ($rows as $row) {
            match ($row['bucket']) {
                'current' => $buckets['current'] = (string) $row['total'],
                '1-30' => $buckets['1-30'] = (string) $row['total'],
                '31-60' => $buckets['31-60'] = (string) $row['total'],
                '61-90' => $buckets['61-90'] = (string) $row['total'],
                default => $buckets['90+'] = (string) $row['total'],
            };
        }

        return $buckets;
    }

    private function applyCustomerSearch(QueryBuilder $qb, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }

        $qb->andWhere('LOWER(c.businessName) LIKE :search')->setParameter(
            'search',
            '%' . mb_strtolower(trim($search)) . '%',
        );
    }

    /**
     * Find a SaleRecord already created for this offline-sale Idempotency-Key
     * header — lets SaleProcessor short-circuit a retried outbox request
     * instead of creating a duplicate sale. See SaleRecord::$offlineRetryKey.
     */
    public function findByOfflineRetryKey(string $offlineRetryKey): ?SaleRecord
    {
        return $this->findOneBy(['offlineRetryKey' => $offlineRetryKey]);
    }

    /**
     * Opening imports are deduplicated tenant-wide, matching the unique index.
     * Return only existence so another branch's sale is never exposed. DBAL
     * deliberately bypasses the branch filter, within the current tenant DB.
     */
    public function hasOfflineRetryKey(string $offlineRetryKey): bool
    {
        return $this
            ->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT 1 FROM sales WHERE offline_retry_key = :key', ['key' => $offlineRetryKey]) !== false;
    }

    /**
     * Enrich a list of SaleRecord entities with tax_document data.
     * Single DBAL query to avoid N+1.
     *
     * @param SaleRecord[] $records
     */
    public function enrichWithTaxDocuments(array $records): void
    {
        if ($records === []) {
            return;
        }

        $ids = [];
        $indexById = [];
        foreach ($records as $record) {
            if ($record->getId() !== null) {
                $ids[] = $record->getId();
                $indexById[$record->getId()] = $record;
            }
        }

        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(sprintf(<<<'SQL'
                SELECT
                    td.tax_document_id,
                    td.movimiento_id,
                    td.sale_id,
                    td.document_type,
                    td.series,
                    td.number,
                    td.sunat_status
                FROM tax_document td
                WHERE (td.movimiento_id IN (%s) OR td.sale_id IN (%s))
                  AND td.reason_code IS NULL
                ORDER BY td.created_at DESC, td.tax_document_id DESC
                SQL, $placeholders, $placeholders), array_merge($ids, $ids));

        // Index by movimiento_id (legacy path) and by sale_id (V2 path)
        $byMovimientoId = [];
        foreach ($rows as $row) {
            // V2 path: index by sale_id
            if (!empty($row['sale_id'])) {
                $saleIdKey = (string) $row['sale_id'];
                if (!isset($byMovimientoId[$saleIdKey])) {
                    $byMovimientoId[$saleIdKey] = $row;
                }
            }
            // Legacy path: index by movimiento_id
            if (!empty($row['movimiento_id'])) {
                $movId = (string) $row['movimiento_id'];
                if (!isset($byMovimientoId[$movId])) {
                    $byMovimientoId[$movId] = $row;
                }
            }
        }

        foreach ($records as $record) {
            $saleId = $record->getId();
            $taxRow = $saleId !== null ? $byMovimientoId[$saleId] ?? null : null;

            if ($taxRow === null) {
                continue;
            }

            $record->setTaxDocumentId((string) $taxRow['tax_document_id']);
            $record->setHasInvoice(true);
            $record->setInvoiceFullNumber(sprintf('%s-%s', $taxRow['series'], $taxRow['number']));
            $record->setSunatStatus($taxRow['sunat_status'] !== null ? (string) $taxRow['sunat_status'] : null);
            $record->setDocumentType((string) $taxRow['document_type']);
            $record->setPublicReceiptUrl(\App\Billing\Receipt\ReceiptPayload::publicUrl(
                (string) $taxRow['document_type'],
                (string) $taxRow['tax_document_id'],
            ));
        }
    }

    /**
     * Enrich a list of SaleRecord entities with their primary payment method
     * and the full payments breakdown (method + amount per line).
     * Single DBAL query to avoid N+1.
     *
     * @param SaleRecord[] $records
     */
    public function enrichWithPaymentMethods(array $records, PaymentMethodRepository $pmRepository): void
    {
        if ($records === []) {
            return;
        }

        $ids = [];
        $indexById = [];
        foreach ($records as $record) {
            if ($record->getId() !== null) {
                $ids[] = $record->getId();
                $indexById[$record->getId()] = $record;
            }
        }

        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this
            ->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(sprintf(<<<'SQL'
                SELECT sp.sale_id, sp.payment_method_id, sp.amount, sp.paid_at, sp.reference
                FROM sale_payments sp
                WHERE sp.sale_id IN (%s)
                ORDER BY sp.sale_id, sp.id ASC
                SQL, $placeholders), $ids);

        $pmIds = array_unique(array_column($rows, 'payment_method_id'));
        $paymentMethods = [];
        if ($pmIds !== []) {
            foreach ($pmRepository->findBy(['id' => $pmIds]) as $pm) {
                if (($id = $pm->getId()) !== null) {
                    $paymentMethods[$id] = $pm;
                }
            }
        }

        // First method per sale becomes the primary paymentMethod; every row
        // is collected into the payments breakdown.
        $assigned = [];
        $paymentsBySale = [];
        foreach ($rows as $row) {
            $saleId = (string) $row['sale_id'];
            $pmId = (int) $row['payment_method_id'];
            $method = $paymentMethods[$pmId] ?? null;

            $paymentsBySale[$saleId][] = [
                'payment_method_id' => $pmId,
                'method_name' => $method?->getName(),
                'amount' => (string) $row['amount'],
                'paid_at' => $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
                'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
            ];

            if (isset($assigned[$saleId])) {
                continue;
            }
            if ($method !== null && isset($indexById[$saleId])) {
                $indexById[$saleId]->setPaymentMethod($method);
                $assigned[$saleId] = true;
            }
        }

        foreach ($paymentsBySale as $saleId => $payments) {
            if (isset($indexById[$saleId])) {
                $indexById[$saleId]->setPayments($payments);
            }
        }
    }
}
