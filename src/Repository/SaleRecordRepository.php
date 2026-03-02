<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SaleRecord;
use App\Repository\PaymentMethodRepository;
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
     * Find a SaleRecord by sale UUID, checking both `sales.id` and `sales.legacy_movimiento_id`.
     */
    public function findByAnyId(string $id): ?SaleRecord
    {
        $result = $this->createQueryBuilder('s')
            ->where('s.id = :id OR s.legacyMovimientoId = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof SaleRecord ? $result : null;
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
        $indexByLegacyId = [];
        foreach ($records as $record) {
            if ($record->getId() !== null) {
                $ids[] = $record->getId();
                $indexById[$record->getId()] = $record;
            }
            if ($record->getLegacyMovimientoId() !== null) {
                $indexByLegacyId[$record->getLegacyMovimientoId()] = $record;
            }
        }

        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            sprintf(
                <<<'SQL'
SELECT
    td.tax_document_id,
    td.movimiento_id,
    td.document_type,
    td.series,
    td.number,
    td.sunat_status
FROM tax_document td
WHERE td.movimiento_id IN (%s)
  AND td.reason_code IS NULL
ORDER BY td.created_at DESC
SQL,
                $placeholders
            ),
            $ids
        );

        // Index by movimiento_id (which equals sales.id in V2)
        $byMovimientoId = [];
        foreach ($rows as $row) {
            $movId = (string) $row['movimiento_id'];
            // Keep only the first (newest) tax document per sale
            if (!isset($byMovimientoId[$movId])) {
                $byMovimientoId[$movId] = $row;
            }
        }

        foreach ($records as $record) {
            $saleId = $record->getId();
            $legacyId = $record->getLegacyMovimientoId();

            $taxRow = $byMovimientoId[$saleId] ?? ($legacyId ? ($byMovimientoId[$legacyId] ?? null) : null);

            if ($taxRow === null) {
                continue;
            }

            $record->setTaxDocumentId((string) $taxRow['tax_document_id']);
            $record->setHasInvoice(true);
            $record->setInvoiceFullNumber(sprintf('%s-%s', $taxRow['series'], $taxRow['number']));
            $record->setSunatStatus($taxRow['sunat_status'] !== null ? (string) $taxRow['sunat_status'] : null);
            $record->setDocumentType((string) $taxRow['document_type']);
            $record->setPublicReceiptUrl(
                \App\Core\Util\ReceiptPayload::publicUrl(
                    (string) $taxRow['document_type'],
                    (string) $taxRow['tax_document_id'],
                )
            );
        }
    }

    /**
     * Enrich a list of SaleRecord entities with their primary payment method.
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
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            sprintf(
                <<<'SQL'
SELECT sp.sale_id, sp.payment_method_id
FROM sale_payments sp
WHERE sp.sale_id IN (%s)
ORDER BY sp.sale_id, sp.id ASC
SQL,
                $placeholders
            ),
            $ids
        );

        $pmIds = array_unique(array_column($rows, 'payment_method_id'));
        $paymentMethods = [];
        if ($pmIds !== []) {
            foreach ($pmRepository->findBy(['id' => $pmIds]) as $pm) {
                $paymentMethods[$pm->getId()] = $pm;
            }
        }

        // Assign first payment method per sale
        $assigned = [];
        foreach ($rows as $row) {
            $saleId = (string) $row['sale_id'];
            if (isset($assigned[$saleId])) {
                continue;
            }
            $pmId = (int) $row['payment_method_id'];
            if (isset($paymentMethods[$pmId], $indexById[$saleId])) {
                $indexById[$saleId]->setPaymentMethod($paymentMethods[$pmId]);
                $assigned[$saleId] = true;
            }
        }
    }
}
