<?php

declare(strict_types=1);

namespace App\Core\Doctrine\Filter;

use Override;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class DevExtremeFilter extends AbstractFilter
{
    /**
     * @inheritdoc
     */
    #[Override]
    public function getDescription(string $resourceClass): array
    {
        return [
            'sort' => [
                'property' => 'sort',
                'type' => 'string',
                'required' => false,
                'description' => 'Sorting parameter.',
            ],
            'filter' => [
                'property' => 'filter',
                'type' => 'array',
                'required' => false,
                'description' => 'Filtering parameter.',
            ],
            'searchValue' => [
                'property' => 'searchValue',
                'type' => 'string',
                'required' => false,
                'description' => 'Search parameter.',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    #[Override]
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ('sort' === $property) {
            $this->applySort($queryBuilder, $value);
        }

        if ('filter' === $property) {
            $this->applyFilter($queryBuilder, $value);
        }

        if ('searchValue' === $property && isset($context['filters']['searchExpr'])) {
            $searchExpr = $context['filters']['searchExpr'];

            if (is_array($searchExpr)) {
                $orX = $queryBuilder->expr()->orX();
                foreach ($searchExpr as $field) {
                    $dqlExpr = $this->resolveFieldExpression($field);
                    $param = $this->getUniqueParameterName($queryBuilder, $field);
                    $orX->add(sprintf('%s LIKE :%s', $dqlExpr, $param));
                    $queryBuilder->setParameter($param, sprintf('%%%s%%', $value));
                }

                $queryBuilder->andWhere($orX);
            } else {
                $dqlExpr = $this->resolveFieldExpression($searchExpr);
                $param = $this->getUniqueParameterName($queryBuilder, $searchExpr);
                $queryBuilder->andWhere(sprintf('%s LIKE :%s', $dqlExpr, $param));
                $queryBuilder->setParameter($param, sprintf('%%%s%%', $value));
            }
        }
    }

    /**
     * Resolves a (possibly virtual) field name to its DQL expression.
     * Virtual fields are computed properties that have no direct ORM mapping.
     *
     * Fields that require a JOIN are handled in applyFilter() before this method
     * is called; this method only covers fields resolvable without extra joins.
     */
    private function resolveFieldExpression(string $field): string
    {
        return match ($field) {
            'fullNumber' => "CONCAT(o.series, '-', o.number)",
            default => sprintf('o.%s', $field),
        };
    }

    /**
     * Returns true when the field requires a JOIN to an external table
     * rather than a direct ORM property reference.
     */
    private function isJoinField(string $field): bool
    {
        return match ($field) {
            'invoiceFullNumber' => true,
            default => false,
        };
    }

    /**
     * Applies a filter condition for a field that lives in a joined table.
     * Adds the necessary LEFT JOIN if it has not been added yet.
     */
    private function applyJoinFieldFilter(
        QueryBuilder $queryBuilder,
        string $field,
        string $op,
        mixed $value,
    ): void {
        if ('invoiceFullNumber' === $field) {
            // Ensure the tax_document join exists (idempotent).
            $aliases = $queryBuilder->getAllAliases();
            if (!in_array('td', $aliases, true)) {
                // TaxDocument has a ManyToOne to SaleRecord via td.sale.
                // We join TaxDocument entities where td.sale = o AND td.reasonCode IS NULL.
                $queryBuilder->leftJoin(
                    \App\Entity\TaxDocument::class,
                    'td',
                    'WITH',
                    'td.sale = o AND td.reasonCode IS NULL'
                );
            }

            $dqlExpr = "CONCAT(td.series, '-', td.number)";
            $operator = $this->getOperator($op);
            $filterValue = $this->getValueForOperator($op, $value);
            $param = $this->getUniqueParameterName($queryBuilder, 'invoiceFullNumber');

            $queryBuilder->andWhere(sprintf('%s %s :%s', $dqlExpr, $operator, $param));
            $queryBuilder->setParameter($param, $filterValue);
        }
    }

    /**
     * @param array<int, mixed> $sort
     */
    private function applySort(QueryBuilder $queryBuilder, array $sort): void
    {
        foreach ($sort as $sortParam) {
            $field = $sortParam['selector'];
            $isDesc = is_bool($sortParam['desc']) ? $sortParam['desc'] : 'true' === $sortParam['desc'];

            $queryBuilder->addOrderBy(
                sprintf('o.%s', $field),
                $isDesc ? 'DESC' : 'ASC'
            );
        }
    }

    /**
     * @param array<int, mixed> $filter
     */
    private function applyFilter(
        QueryBuilder $queryBuilder,
        array $filter,
    ): void {
        foreach ($filter as $filterParam) {
            if (is_string($filterParam)) {
                continue;
            }

            if (is_array($filterParam[0])) {
                $this->applyFilter($queryBuilder, $filterParam);
                continue;
            }

            if (!array_key_exists(1, $filterParam)) {
                continue;
            }

            if (!array_key_exists(2, $filterParam) && !in_array($filterParam[1], ['isnull', 'isnotnull'], true)) {
                continue;
            }

            $field = $filterParam[0];
            $op = $filterParam[1];

            if ($this->isJoinField($field)) {
                $this->applyJoinFieldFilter($queryBuilder, $field, $op, $filterParam[2] ?? null);
                continue;
            }

            if ('isnull' === $op || 'isnotnull' === $op) {
                $operator = $this->getOperator($op);
                $queryBuilder->andWhere(
                    sprintf('o.%s %s', $field, $operator)
                );
            } elseif ('in' === $op) {
                $uniqueParameterName = $this->getUniqueParameterName(
                    $queryBuilder,
                    $field
                );
                $queryBuilder->andWhere(
                    sprintf('o.%s IN (:%s)', $field, $uniqueParameterName)
                )
                    ->setParameter($uniqueParameterName, $filterParam[2]);
            } else {
                $operator = $this->getOperator($op);
                $value = $this->getValueForOperator($op, $filterParam[2] ?? null);
                $uniqueParameterName = $this->getUniqueParameterName(
                    $queryBuilder,
                    $field
                );
                $queryBuilder->andWhere(
                    sprintf('o.%s %s :%s', $field, $operator, $uniqueParameterName)
                )
                    ->setParameter($uniqueParameterName, $value);
            }
        }
    }

    private function getUniqueParameterName(
        QueryBuilder $queryBuilder,
        string $field,
    ): string {
        $index = 1;
        $parameterName = $field;

        while ($queryBuilder->getParameter($parameterName)) {
            $parameterName = $field . $index++;
        }

        return $parameterName;
    }

    private function getValueForOperator(string $op, mixed $value): string|int|float|null
    {
        return match ($op) {
            'contains', 'notcontains' => sprintf('%%%s%%', $value),
            'startswith' => $value . '%',
            'endswith' => '%' . $value,
            'isnull', 'isnotnull' => null,
            default => $value,
        };
    }

    private function getOperator(string $op): string
    {
        return match ($op) {
            'contains', 'startswith', 'endswith' => 'LIKE',
            'notcontains' => 'NOT LIKE',
            'isnull' => 'IS NULL',
            'isnotnull' => 'IS NOT NULL',
            default => $op,
        };
    }
}
