<?php

namespace App\Core\Infrastructure\DataLoader;

class OptionsBuilder
{
    /**
     * @template E of object
     * @template M of object
     *
     * @param array<string, array<string, bool|float|int|string|null>> $context
     * @param class-string<E>                                          $entity
     * @param class-string<M>                                          $mapping
     */
    public static function build(array $context, string $entity, string $mapping): Options
    {
        /** @var string $joinRules */
        $joinRules = $context['filters']['joinRules'] ?? '[]';
        /** @var array<int, array<string, bool|float|int|string|null>> $joinRules */
        $joinRules = json_decode(strval($joinRules), true);

        /** @var string|null $sortFields */
        $sortFields = $context['filters']['sort'] ?? null;
        /** @var string|null $orderFields */
        $orderFields = $context['filters']['order'] ?? null;
        $sortArray = $sortFields ? explode(',', strval($sortFields)) : [];
        $orderArray = $orderFields ? explode(',', strval($orderFields)) : [];
        $sortRules = array_map(null, $sortArray, $orderArray);

        /** @var string $filterRules */
        $filterRules = $context['filters']['filterRules'] ?? '[]';
        /** @var array<int, array<string, bool|float|int|string|null>> $filterRules */
        $filterRules = json_decode(strval($filterRules), true);

        return new Options(
            entity: $entity,
            mapping: $mapping,
            page: intval($context['filters']['page'] ?? 1),
            rows: intval($context['filters']['rows'] ?? 10),
            joins: self::buildJoins($joinRules),
            sort: self::buildSort($sortRules),
            filters: self::buildFilters($filterRules)
        );
    }

    /**
     * @param array<int, array<string, bool|float|int|string|null>> $joinRules
     *
     * @return array<JoinRule>
     */
    private static function buildJoins(array $joinRules): array
    {
        $joins = [];
        foreach ($joinRules as $join) {
            $joins[] = new JoinRule(
                entity: strval($join['entity']),
                alias: strval($join['alias']),
                type: JoinType::tryFrom(strval($join['type'])) ?? JoinType::INNER,
                condition: strval($join['condition']),
            );
        }

        return $joins;
    }

    /**
     * @param array<int, array<int, bool|float|int|string|null>> $sortRules
     *
     * @return array<SortRule>
     */
    private static function buildSort(array $sortRules): array
    {
        return array_map(fn ($item) => new SortRule(
            field: strval($item[0]),
            order: strval($item[1] ?? 'ASC'),
        ), $sortRules);
    }

    /**
     * @param array<int, array<string, bool|float|int|string|null>> $filterRules
     *
     * @return array<FilterRule>
     */
    private static function buildFilters(array $filterRules): array
    {
        $filters = [];
        foreach ($filterRules as $filter) {
            $op = FilterOperation::tryFrom(strval($filter['op'])) ?? FilterOperation::Contains;
            $filters[] = new FilterRule(
                field: strval($filter['field']),
                op: $op,
                value: $filter['value'],
            );
        }

        return $filters;
    }
}
