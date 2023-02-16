<?php

namespace App\Core\Infrastructure\DataLoader;

class OptionsBuilder
{
    /**
     * @param array<string, string> $context
     * @param string $entity
     * @param string $mapping
     *
     * @return Options
     */
    public static function build(array $context, string $entity, string $mapping): Options
    {
        $joinRules = $context['filters']['joinRules'] ?? '[]';
        $joinRules = json_decode($joinRules, true);

        $sortFields = $context['filters']['sort'] ?? null;
        $orderFields = $context['filters']['order'] ?? null;
        $sortArray = $sortFields ? explode(',', $sortFields) : [];
        $orderArray = $orderFields ? explode(',', $orderFields) : [];
        $sortRules = array_map(null, $sortArray, $orderArray);

        $filterRules = $context['filters']['filterRules'] ?? '[]';
        $filterRules = json_decode($filterRules, true);

        return new Options(
            entity: $entity,
            mapping: $mapping,
            page: $context['filters']['page'] ?? 1,
            rows: $context['filters']['rows'] ?? 10,
            joins: self::buildJoins($joinRules),
            sort: self::buildSort($sortRules),
            filters: self::buildFilters($filterRules)
        );
    }

    private static function buildJoins(array $joinRules): array
    {
        $joins = [];
        foreach ($joinRules as $join) {
            $joins[] = new JoinRule(
                entity: $join['entity'],
                alias: $join['alias'],
                type: JoinType::tryFrom($join['type']),
                condition: $join['condition'],
            );
        }

        return $joins;
    }

    /**
     * @param array<string, string> $sortRules
     *
     * @return array<SortRule>
     */
    private static function buildSort(array $sortRules): array
    {
        return array_map(fn($item) => new SortRule(
            field: $item[0],
            order: $item[1] ?? 'ASC',
        ), $sortRules);
    }

    /**
     * @param array<string, array<string, string>> $filterRules
     *
     * @return array<FilterRule>
     */
    private static function buildFilters(array $filterRules): array
    {
        $filters = [];
        foreach ($filterRules as $filter) {
            $op = FilterOperation::tryFrom($filter['op']);
            $filters[] = new FilterRule(
                field: $filter['field'],
                op: $op,
                value: $filter['value'],
            );
        }

        return $filters;
    }
}