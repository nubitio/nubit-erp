<?php

namespace App\Core\Infrastructure\Common\DataLoader;

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
        return new Options(
            entity: $entity,
            page: $this->request->get('page', 1),
            rows: $this->request->get('rows', 10),
            sort: $this->buildSort(),
            filters: $this->buildFilters(),
        );
    }

    private function buildSort(): array
    {
        $sort = $this->request->get('sort', []);
        $sort = is_array($sort) ? $sort : [$sort];

        return array_map(fn($item) => new Sort(
            field: $item['field'],
            order: $item['order'],
        ), $sort);
    }

    private function buildFilters(): array
    {
        if (!$this->request->get('filterRules')) {
            return [];
        }

        $filterRules = json_decode($this->request->get('filterRules'), true);
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