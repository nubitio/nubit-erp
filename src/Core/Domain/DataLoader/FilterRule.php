<?php

namespace App\Core\Domain\DataLoader;

use App\Core\Infrastructure\DataLoader\FilterUtils;
use Doctrine\ORM\QueryBuilder;

class FilterRule
{
    public function __construct(
        public string $field,
        public FilterOperation $op,
        public bool|float|int|string|null $value = null,
        public FilterType $type = FilterType::AND,
        /** @var array<FilterRule>|null */
        public ?array $subFilters = null,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $op = FilterUtils::getOperation($this->op);
        $uniqueParam = FilterUtils::getUniqueParam($queryBuilder, $this->field);
        $field = 'e.' . $this->field;
        if (str_contains($this->field, '.')) {
            $field = $this->field;
        }

        if (FilterOperation::IsNull === $this->op || FilterOperation::IsNotNull === $this->op) {
            if (FilterType::OR === $this->type) {
                $queryBuilder->orWhere(sprintf('%s %s', $field, $op));
            } else {
                $queryBuilder->andWhere(sprintf('%s %s', $field, $op));
            }

            return;
        }

        if (FilterType::OR === $this->type) {
            $queryBuilder->orWhere(sprintf('%s %s :%s', $field, $op, $uniqueParam));
        } else {
            $queryBuilder->andWhere(sprintf('%s %s :%s', $field, $op, $uniqueParam));
        }

        $value = strval($this->value);
        if (FilterOperation::Contains === $this->op) {
            $queryBuilder->setParameter($uniqueParam, sprintf('%%%s%%', $value));
        } elseif (FilterOperation::BeginsWith === $this->op) {
            $queryBuilder->setParameter($uniqueParam, $value . '%');
        } elseif (FilterOperation::EndsWith === $this->op) {
            $queryBuilder->setParameter($uniqueParam, '%' . $value);
        } else {
            $queryBuilder->setParameter($uniqueParam, $this->value);
        }
    }
}
