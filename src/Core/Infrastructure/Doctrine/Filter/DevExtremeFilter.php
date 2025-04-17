<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Doctrine\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class DevExtremeFilter extends AbstractFilter
{
    /**
     * @inheritdoc
     */
    #[\Override]
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
    #[\Override]
    protected function filterProperty(
        string $property,
        $value,
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
            $filter = [];
            $filter[] = [
                $context['filters']['searchExpr'],
                'contains',
                $value,
            ];
            $this->applyFilter($queryBuilder, $filter);
        }
    }

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

            $field = $filterParam[0];
            $operator = $this->getOperator($filterParam[1]);
            $value = $this->getValueForOperator(
                $filterParam[1],
                $filterParam[2]
            );

            $uniqueParameterName = $this->getUniqueParameterName(
                $queryBuilder,
                $field
            );
            if ('isnull' === $filterParam[1] || 'isnotnull' === $filterParam[1]) {
                $queryBuilder->andWhere(
                    sprintf('o.%s %s', $field, $operator)
                );
            } else {
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

    private function getValueForOperator(string $op, string $value): string
    {
        return match ($op) {
            'contains', 'notcontains' => sprintf('%%%s%%', $value),
            'startswith' => $value . '%',
            'endswith' => '%' . $value,
            'isnull', 'isnotnull' => '',
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
