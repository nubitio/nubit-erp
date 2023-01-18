<?php

namespace App\Core\Infrastructure\DataLoader;

use Symfony\Component\HttpFoundation\Request;

readonly class OptionsBuilder
{
    public function __construct(
        private Request $request,
    )
    {
    }

    public function build(string $entity): Options
    {
        $sortRules = $this->request->get('sort', []);
        $sortRules = is_array($sortRules) ? $sortRules : [$sortRules];

        if (!$this->request->get('filterRules')) {
            $filterRules = [];
        } else {
            $filterRules = json_decode($this->request->get('filterRules'), true);
        }

        return new Options(
            entity: $entity,
            page: $this->request->get('page', 1),
            rows: $this->request->get('rows', 10),
            sort: $this->buildSort($sortRules),
            filters: $this->buildFilters($filterRules),
        );
    }

    public static function buildSort(array $sortRules): array
    {
        return array_map(fn($item) => new Sort(
            field: $item['field'],
            order: $item['order'],
        ), $sortRules);
    }

    public static function buildFilters(array $filterRules): array
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