<?php

namespace App\Core\Infrastructure\DataLoader;

class OptionsBuilder
{
    public static function build(array $context, string $entity): Options
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

    private static function buildSort(array $sortRules): array
    {
        return array_map(fn($item) => new Sort(
            field: $item[0],
            order: $item[1] ?? 'ASC',
        ), $sortRules);
    }

    private static function buildFilters(array $filterRules): array
    {
        $filters = [];
        foreach ($filterRules as $filter) {
            $op = FilterOperation::tryFrom($filter['op']);
            $filters[] = new Filter(
                field: $filter['field'],
                op: $op,
                value: $filter['value'],
            );
        }

        return $filters;
    }
}