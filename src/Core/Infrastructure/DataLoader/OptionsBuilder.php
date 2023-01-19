<?php

namespace App\Core\Infrastructure\DataLoader;

class OptionsBuilder
{
    public static function build(array $context, string $entity): Options
    {
        $sortRules = $context['filters']['order'] ?? [];
        $sortRules = is_array($sortRules) ? $sortRules : [$sortRules];

        $filterRules = $context['filters']['filterRules'] ?? "[]";
        $filterRules = json_decode($filterRules, true);

        return new Options(
            entity: $entity,
            page: $context['filters']['page'] ?? 1,
            rows: $context['filters']['rows'] ?? 10,
            sort: self::buildSort($sortRules),
            filters: self::buildFilters($filterRules),
        );
    }

    private static function buildSort(array $sortRules): array
    {
        return array_map(fn($item) => new Sort(
            field: $item['field'],
            order: $item['order'],
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