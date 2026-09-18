<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Sales\Entity\SaleRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
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
     * Find a SaleRecord already created for this offline-sale Idempotency-Key
     * header — lets SaleProcessor short-circuit a retried outbox request
     * instead of creating a duplicate sale. See SaleRecord::$offlineRetryKey.
     */
    public function findByOfflineRetryKey(string $offlineRetryKey): ?SaleRecord
    {
        return $this->findOneBy(['offlineRetryKey' => $offlineRetryKey]);
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
                ORDER BY td.created_at DESC
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
            $taxRow = $saleId !== null ? ($byMovimientoId[$saleId] ?? null) : null;

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
                $paymentMethods[$pm->getId()] = $pm;
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
