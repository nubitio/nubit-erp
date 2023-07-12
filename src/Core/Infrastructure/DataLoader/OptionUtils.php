<?php

namespace App\Core\Infrastructure\DataLoader;

class OptionUtils
{
    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    public static function addSort(array $context, SortRule $sort): array
    {
        /** @var array<int, string> $sortRules */
        $sortRules = $context['filters']['sort'] ?? [];

        $sortRule = [
            'selector' => $sort->field,
            'desc' => 'desc' === $sort->order,
        ];

        $sortRules = [json_encode($sortRule), ...$sortRules];

        $context['filters']['sort'] = array_unique($sortRules);

        /* @phpstan-ignore-next-line */
        return $context;
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    public static function addFilter(array $context, FilterRule $filter): array
    {
        $context['filters']['filterRules'] = $context['filters']['filterRules'] ?? '[]';
        /** @var array<int, array<string, mixed>> $filterRules */
        $filterRules = json_decode(strval($context['filters']['filterRules']), true);
        $filterRules[] = [
            'field' => $filter->field,
            'op' => $filter->op->value,
            'value' => $filter->value,
        ];
        $context['filters']['filterRules'] = json_encode($filterRules);

        return $context;
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    public static function addJoin(array $context, JoinRule $join): array
    {
        $context['filters']['joinRules'] = $context['filters']['joinRules'] ?? '[]';
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
