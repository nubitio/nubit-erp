<?php

declare(strict_types=1);

namespace App\Billing\Doctrine\Filter;

use App\Billing\Entity\TaxDocument;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\QueryBuilder;
use Nubit\ApiPlatform\Doctrine\Filter\GridFilterHelper;
use Nubit\ApiPlatform\Doctrine\Filter\GridVirtualFieldInterface;

/**
 * Virtual grid fields for the sales/billing resources:
 *
 * - `fullNumber`          → CONCAT(o.series, '-', o.number) on the resource itself
 * - `invoiceFullNumber`   → series-number of the linked TaxDocument (LEFT JOIN)
 * - `paymentMethod`       → subquery over SalePayment (no ORM association)
 */
final class SalesGridVirtualFields implements GridVirtualFieldInterface
{
    public function supports(string $resourceClass, string $field): bool
    {
        if (in_array($field, ['paymentState', 'sunatState'], true)) {
            return $resourceClass === SaleRecord::class;
        }
        return in_array($field, ['fullNumber', 'invoiceFullNumber', 'paymentMethod'], strict: true);
    }

    public function expression(QueryBuilder $queryBuilder, string $resourceClass, string $field): ?string
    {
        return match ($field) {
            'fullNumber' => "CONCAT(o.series, '-', o.number)",
            'invoiceFullNumber' => $this->joinedInvoiceExpression($queryBuilder),
            'paymentState'
                => "CASE WHEN o.status = 'VOIDED' THEN 'voided' WHEN o.balanceDue > 0 THEN 'due' ELSE 'paid' END",
            default => null,
        };
    }

    public function applyFilter(
        QueryBuilder $queryBuilder,
        string $resourceClass,
        string $field,
        string $operator,
        mixed $value,
    ): void {
        if ('sunatState' === $field) {
            if (!in_array($value, ['accepted', 'pending', 'not_applicable'], true) || $operator !== '=') {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Filtro SUNAT no válido.');
            }
            // Only the latest original document counts. Credit notes and superseded
            // attempts must not duplicate a sale or override its current status.
            $latest = sprintf(
                'SELECT st.id FROM %s st WHERE (st.saleRecord = o OR st.legacyMovementId = o.id) AND st.reasonCode IS NULL AND NOT EXISTS (SELECT newer.id FROM %s newer WHERE (newer.saleRecord = o OR newer.legacyMovementId = o.id) AND newer.reasonCode IS NULL AND (newer.createdAt > st.createdAt OR (newer.createdAt = st.createdAt AND newer.id > st.id)))',
                TaxDocument::class,
                TaxDocument::class,
            );
            if ($value === 'accepted')
                $latest .= " AND st.sunatStatus = '0'";
            if ($value === 'pending')
                $latest .= " AND (st.sunatStatus IS NULL OR st.sunatStatus <> '0')";
            $queryBuilder->andWhere(sprintf('%sEXISTS (%s)', $value === 'not_applicable' ? 'NOT ' : '', $latest));
            return;
        }
        if ('paymentState' === $field) {
            if (!in_array($value, ['paid', 'due', 'voided'], true) || $operator !== '=') {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Filtro de pago no válido.');
            }
            $param = GridFilterHelper::uniqueParameterName($queryBuilder, $field);
            $queryBuilder->andWhere(
                $this->expression($queryBuilder, $resourceClass, $field) . ' = :' . $param,
            )->setParameter($param, $value);
            return;
        }
        if ('fullNumber' === $field || 'invoiceFullNumber' === $field) {
            $dqlExpr = $this->expression($queryBuilder, $resourceClass, $field);
            $dqlOperator = GridFilterHelper::dqlOperator($operator);
            $filterValue = GridFilterHelper::valueForOperator($operator, $value);
            $param = GridFilterHelper::uniqueParameterName($queryBuilder, $field);

            $queryBuilder->andWhere(sprintf('%s %s :%s', $dqlExpr, $dqlOperator, $param));
            $queryBuilder->setParameter($param, $filterValue);

            return;
        }

        if ('paymentMethod' === $field) {
            // sale_payments has no direct ORM association on SaleRecord.
            // Use a DQL subquery via the SalePayment entity.
            $param = GridFilterHelper::uniqueParameterName($queryBuilder, 'paymentMethod');
            $dqlOperator = GridFilterHelper::dqlOperator($operator);
            $filterValue = GridFilterHelper::valueForOperator($operator, $value);

            $subDql = sprintf(
                'SELECT IDENTITY(sp.saleRecord) FROM %s sp WHERE sp.paymentMethod %s :%s',
                SalePayment::class,
                $dqlOperator,
                $param,
            );

            $queryBuilder->andWhere(sprintf('o.id IN (%s)', $subDql));
            $queryBuilder->setParameter($param, $filterValue);
        }
    }

    /**
     * Ensures the tax_document join exists (idempotent) and returns the
     * series-number expression on the joined alias.
     */
    private function joinedInvoiceExpression(QueryBuilder $queryBuilder): string
    {
        $aliases = $queryBuilder->getAllAliases();
        if (!in_array('td', $aliases, strict: true)) {
            // TaxDocument has a ManyToOne to SaleRecord via td.saleRecord.
            // Join TaxDocument entities where td.saleRecord = o AND td.reasonCode IS NULL.
            $queryBuilder->leftJoin(TaxDocument::class, 'td', 'WITH', 'td.saleRecord = o AND td.reasonCode IS NULL');
        }

        return "CONCAT(td.series, '-', td.number)";
    }
}
