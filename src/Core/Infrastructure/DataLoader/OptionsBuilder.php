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

        /** @var string|array<int, string>|null $sortFields */
        $sortFields = $context['filters']['sort'] ?? null;
        $strSortFields = '';
        $strOrderFields = '';
        if (is_array($sortFields)) {
            foreach ($sortFields as $sortField) {
                /** @var array<string, string> $sortRule */
                $sortRule = json_decode($sortField, true);
                $strSortFields .= $sortRule['selector'].',';
                $strOrderFields .= $sortRule['desc'] ? 'desc,' : 'asc,';
            }
            $sortFields = $strSortFields ? substr($strSortFields, 0, -1) : null;
        }
        /** @var string|null $orderFields */
        $orderFields = $context['filters']['order'] ?? null;
        if (!empty($strOrderFields)) {
            $orderFields = substr($strOrderFields, 0, -1);
        }
        $sortArray = $sortFields ? explode(',', $sortFields) : [];
        $orderArray = $orderFields ? explode(',', strval($orderFields)) : [];
        $sortRules = array_map(null, $sortArray, $orderArray);

        /** @var string $filterRules */
        $filterRules = $context['filters']['filterRules'] ?? '[]';
        /** @var array<int, array<string, bool|float|int|string|null>> $filterRules */
        $filterRules = json_decode(strval($filterRules), true);

        /** @var array<int, string> $filters */
        $filters = $context['filters']['filter'] ?? [];

        foreach ($filters as $filter) {
            $filterRule = json_decode($filter, true);
            if (!is_array($filterRule)) {
                continue;
            }
            $filterRules[] = [
                'field' => $filterRule[0],
                'op' => $filterRule[1],
                'value' => $filterRule[2] ?? '',
            ];
        }
        if (3 === count($filters) && !is_array(json_decode($filters[0], true))) {
            $filterRules[] = [
                'field' => $filters[0],
                'op' => $filters[1],
                'value' => $filters[2],
            ];
        }

        $searchOperation = $context['filters']['searchOperation'] ?? null;
        $searchExpression = $context['filters']['searchExpr'] ?? null;
        $searchValue = $context['filters']['searchValue'] ?? null;

        if ($searchOperation && $searchExpression && $searchValue) {
            $filterRules[] = [
                'field' => $searchExpression,
                'op' => $searchOperation,
                'value' => $searchValue,
            ];
        }

        return new Options(
            entity: $entity,
            mapping: $mapping,
            page: intval($context['filters']['page'] ?? 1),
            rows: intval($context['filters']['rows'] ?? 10),
            skip: intval($context['filters']['skip'] ?? 0),
            take: intval($context['filters']['take'] ?? 0),
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
            $op = FilterOperation::tryFrom(strval($filter['op'])) ?? FilterOperation::fromString(strval($filter['op']));
            if ('true' === $filter['value']) {
                $filter['value'] = true;
            } elseif ('false' === $filter['value']) {
                $filter['value'] = false;
            }
            $filters[] = new FilterRule(
                field: strval($filter['field']),
                op: $op,
                value: $filter['value'],
            );
        }

        return $filters;
    }
}
