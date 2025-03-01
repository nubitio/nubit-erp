<?php

namespace App\Core\Domain\DataLoader;

class OptionsBuilder
{
    /**
     * @template E of object
     * @template M of object
     *
     * @param array<string, array<string, mixed>> $context
     * @param class-string<E>                     $entity
     * @param class-string<M>                     $mapping
     */
    public static function build(array $context, string $entity, string $mapping): LoadOptions
    {
        /** @var string $joinRules */
        $joinRules = $context['filters']['joinRules'] ?? '[]';
        /** @var array<int, array<string, bool|float|int|string|null>> $joinRules */
        $joinRules = json_decode(strval($joinRules), true);

        /** @var string|array<int, mixed>|null $sortFields */
        $sortFields = $context['filters']['sort'] ?? null;
        $strSortFields = '';
        $strOrderFields = '';
        if (is_array($sortFields)) {
            foreach ($sortFields as $sortField) {
                /** @var array<string, mixed> $sortRule */
                $sortRule = is_array($sortField) ? $sortField : json_decode((string)$sortField, true);
                $strSortFields .= $sortRule['selector'] . ',';
                $strOrderFields .= boolval($sortRule['desc']) ? 'desc,' : 'asc,';
            }

            $sortFields = $strSortFields !== '' && $strSortFields !== '0' ? substr($strSortFields, 0, -1) : null;
        }

        /** @var string|null $orderFields */
        $orderFields = $context['filters']['order'] ?? null;
        if ($strOrderFields !== '' && $strOrderFields !== '0') {
            $orderFields = substr($strOrderFields, 0, -1);
        }

        $sortArray = $sortFields ? explode(',', $sortFields) : [];
        $orderArray = $orderFields ? explode(',', strval($orderFields)) : [];
        $sortRules = array_map(null, $sortArray, $orderArray);

        /** @var string $filterRules */
        $filterRules = $context['filters']['filterRules'] ?? '[]';
        /** @var array<int, array<string, bool|float|int|string|null>> $filterRules */
        $filterRules = json_decode(strval($filterRules), true);

        /** @var array<int, mixed> $filters */
        $filters = $context['filters']['filter'] ?? [];

        foreach ($filters as $filter) {
            $filterRule = is_array($filter) ? $filter : json_decode((string)$filter, true);
            if (!is_array($filterRule)) {
                continue;
            }

            if (is_array($filterRule[0])) {
                foreach ($filterRule as $rule) {
                    if (!is_array($rule)) {
                        continue;
                    }

                    $filterRules[] = [
                        'field' => $rule[0],
                        'op' => $rule[1],
                        'value' => $rule[2],
                    ];
                }
            } else {
                $filterRules[] = [
                    'field' => $filterRule[0] ?? $filterRule['field'] ?? '',
                    'op' => $filterRule[1] ?? $filterRule['operator'] ?? '',
                    'value' => $filterRule[2] ?? $filterRule['value'] ?? '',
                ];
            }
        }

        if (
            3 === count($filters) && !is_array(
                is_array($filters[0]) ? $filters[0] : json_decode((string)$filters[0], true)
            )
        ) {
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
            if (is_array($searchExpression)) {
                $index = 0;
                $type = 'and';
                $filter = self::buildSearchRule($searchExpression[$index], $searchOperation, $searchValue, $type);
                $subFilters = [];
                foreach ($searchExpression as $expression) {
                    if ($index > 0) {
                        $type = 'or';
                        $subFilters[] = self::buildSearchRule($expression, $searchOperation, $searchValue, $type);
                    }

                    ++$index;
                }

                $filter['subFilters'] = $subFilters;
                $filterRules[] = $filter;
            } else {
                $filterRules[] = self::buildSearchRule($searchExpression, $searchOperation, $searchValue);
            }
        }

        return new LoadOptions(
            entity: $entity,
            mapping: $mapping,
            skip: $context['filters']['skip'] ?? null,
            take: $context['filters']['take'] ?? null,
            joins: self::buildJoins($joinRules),
            sort: self::buildSort($sortRules),
            filters: self::buildFilters($filterRules)
        );
    }

    private static function buildSearchRule(string $field, string $op, string $value, string $type = 'and'): array
    {
        return [
            'field' => $field,
            'op' => $op,
            'value' => $value,
            'type' => $type,
        ];
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
        return array_map(fn ($item): \App\Core\Domain\DataLoader\SortRule => new SortRule(
            field: strval($item[0]),
            order: strval($item[1] ?? 'ASC'),
        ), $sortRules);
    }

    /**
     * @param array<int, array<string, mixed>> $filterRules
     *
     * @return array<FilterRule>
     */
    private static function buildFilters(array $filterRules): array
    {
        $filters = [];
        foreach ($filterRules as $filter) {
            if ('' === $filter['field']) {
                continue;
            }
            if ('' === $filter['op']) {
                continue;
            }
            if ('or' === $filter['op']) {
                continue;
            }

            $op = FilterOperation::tryFrom(strval($filter['op'])) ?? FilterOperation::fromString(strval($filter['op']));
            if ('true' === $filter['value']) {
                $filter['value'] = true;
            } elseif ('false' === $filter['value']) {
                $filter['value'] = false;
            }

            $subFilters = $filter['subFilters'] ?? null;
            if (is_array($subFilters)) {
                $filters[] = new FilterRule(
                    field: strval($filter['field']),
                    op: $op,
                    value: $filter['value'],
                    type: FilterType::tryFrom(strval($filter['type'] ?? 'and')) ?? FilterType::AND,
                    subFilters: self::buildFilters($subFilters),
                );
                continue;
            }

            if (is_array($filter['field'])) {
                continue;
            }
            if (is_array($filter['value'])) {
                continue;
            }

            $filters[] = new FilterRule(
                field: strval($filter['field']),
                op: $op,
                value: $filter['value'],
                type: FilterType::tryFrom(strval($filter['type'] ?? 'and')) ?? FilterType::AND,
            );
        }

        return $filters;
    }
}
