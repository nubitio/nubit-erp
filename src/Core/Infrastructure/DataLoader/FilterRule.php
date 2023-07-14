<?php

namespace App\Core\Infrastructure\DataLoader;

use Doctrine\ORM\QueryBuilder;

class FilterRule implements FilterInterface
{
    public function __construct(
        public string $field,
        public FilterOperation $op,
        public bool|float|int|string|null $value = null,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $op = FilterUtils::getOperation($this->op);
        $uniqueParam = FilterUtils::getUniqueParam($queryBuilder, $this->field);
        $field = 'e.'.$this->field;
        if (str_contains($this->field, '.')) {
            $field = $this->field;
        }
        if (FilterOperation::IsNull === $this->op || FilterOperation::IsNotNull === $this->op) {
            $queryBuilder->andWhere("$field $op");

            return;
        }
        $queryBuilder->andWhere("$field $op :$uniqueParam");

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
