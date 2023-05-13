<?php

namespace App\Core\Infrastructure\DataLoader;

use Doctrine\ORM\QueryBuilder;

class FilterRule implements FilterInterface
{
    public function __construct(
        public string $field,
        public FilterOperation $op,
        public bool|float|int|string|null $value,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $op = FilterUtils::getOperation($this->op);
        $uniqueParam = FilterUtils::getUniqueParam($queryBuilder, $this->field);
        if (str_contains($this->field, '.')) {
            $queryBuilder->andWhere("$this->field $op :$uniqueParam");
        } else {
            $queryBuilder->andWhere("e.$this->field $op :$uniqueParam");
        }

        $value = strval($this->value);
        if (FilterOperation::Contains === $this->op) {
            $queryBuilder->setParameter($uniqueParam, "%{$value}%");
        } elseif (FilterOperation::BeginsWith === $this->op) {
            $queryBuilder->setParameter($uniqueParam, "{$value}%");
        } elseif (FilterOperation::EndsWith === $this->op) {
            $queryBuilder->setParameter($uniqueParam, "%{$value}");
        } else {
            $queryBuilder->setParameter($uniqueParam, $this->value);
        }
    }
}
