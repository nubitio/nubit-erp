<?php

declare(strict_types=1);

namespace App\Efact\Doctrine\Filter;

use App\Billing\Entity\TaxDocument;
use App\Sales\Entity\SalePayment;
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
        return in_array($field, ['fullNumber', 'invoiceFullNumber', 'paymentMethod'], true);
    }

    public function expression(QueryBuilder $queryBuilder, string $resourceClass, string $field): ?string
    {
        return match ($field) {
            'fullNumber' => "CONCAT(o.series, '-', o.number)",
            'invoiceFullNumber' => $this->joinedInvoiceExpression($queryBuilder),
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
        if (!in_array('td', $aliases, true)) {
            // TaxDocument has a ManyToOne to SaleRecord via td.saleRecord.
            // Join TaxDocument entities where td.saleRecord = o AND td.reasonCode IS NULL.
            $queryBuilder->leftJoin(TaxDocument::class, 'td', 'WITH', 'td.saleRecord = o AND td.reasonCode IS NULL');
        }

        return "CONCAT(td.series, '-', td.number)";
    }
}
