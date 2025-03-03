<?php

namespace App\Core\Domain\DataLoader;

class OptionUtils
{
    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    public static function addSort(array $context, SortRule $sort): array
    {
        /** @var array<int, mixed> $sortRules */
        $sortRules = $context['filters']['sort'] ?? [];

        foreach ($sortRules as $sortRule) {
            $sortRule = is_array($sortRule) ? $sortRule : json_decode((string)$sortRule, true);
            if ($sortRule['selector'] === $sort->field) {
                $sortRule['desc'] = 'desc' === $sort->order;
                $sortRules = [$sortRule, ...$sortRules];
                $context['filters']['sort'] = array_unique($sortRules, SORT_REGULAR);

                return $context;
            }
        }

        $sortRule = [
            'selector' => $sort->field,
            'desc' => 'desc' === $sort->order,
        ];

        $sortRules = [$sortRule, ...$sortRules];

        $context['filters']['sort'] = array_unique($sortRules, SORT_REGULAR);

        return $context;
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    public static function addFilter(array $context, FilterRule $filter): array
    {
        $context['filters']['filter'] ??= [];
        /** @var array<int, array<string, mixed>> $filterRules */
        $filterRules = $context['filters']['filter'];
        if (!empty($filterRules)) {
            $filterRules[] = "and";
        }
        $filterRules[] = [
            $filter->field,
            $filter->op->value,
            strval($filter->value),
        ];
        $context['filters']['filter'] = $filterRules;

        return $context;
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    public static function addJoin(array $context, JoinRule $join): array
    {
        $context['filters']['joinRules'] ??= '[]';
        /** @var array<int, array<string, mixed>> $joinRules */
        $joinRules = json_decode(strval($context['filters']['joinRules']), true);
        $joinRules[] = [
            'entity' => $join->entity,
            'alias' => $join->alias,
            'type' => $join->type,
            'condition' => $join->condition,
        ];
        $context['filters']['joinRules'] = json_encode($joinRules);

        return $context;
    }
}
