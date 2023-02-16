<?php

namespace App\Core\Infrastructure\DataLoader;

class OptionUtils
{
    /**
     * @param array<string, mixed> $context
     * @param SortRule $sort
     *
     * @return array<string, mixed>
     */
    public static function addSort(array $context, SortRule $sort): array
    {
        $context['filters']['sort'] = $context['filters']['sort'] ?? $sort->field;
        $context['filters']['order'] = $context['filters']['order'] ?? $sort->order;

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @param FilterRule $filter
     *
     * @return array<string, mixed>
     */
    public static function addFilter(array $context, FilterRule $filter): array
    {
        $context['filters']['filterRules'] = $context['filters']['filterRules'] ?? '[]';
        $filterRules = json_decode($context['filters']['filterRules'], true);
        $filterRules[] = [
            'field' => $filter->field,
            'op'    => $filter->op->value,
            'value' => $filter->value,
        ];
        $context['filters']['filterRules'] = json_encode($filterRules);

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @param JoinRule $join
     *
     * @return array<string, mixed>
     */
    public static function addJoin(array $context, JoinRule $join): array
    {
        $context['filters']['joinRules'] = $context['filters']['joinRules'] ?? '[]';
        $joinRules = json_decode($context['filters']['joinRules'], true);
        $joinRules[] = [
            'entity'    => $join->entity,
            'alias'     => $join->alias,
            'type'      => $join->type,
            'condition' => $join->condition,
        ];
        $context['filters']['joinRules'] = json_encode($joinRules);

        return $context;
    }
}