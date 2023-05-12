<?php

namespace App\Core\Infrastructure\DataLoader;

use Doctrine\ORM\QueryBuilder;

class FilterRule implements FilterInterface
{
    public function __construct(
        public string          $field,
        public FilterOperation $op,
        public mixed           $value,
    )
    {
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $op = FilterUtils::getOperation($this->op);
        $uniqueParam = FilterUtils::getUniqueParam($queryBuilder, $this->field);
        $field = 'e.' . $this->field;
        if (str_contains($this->field, '.')) {
            $field = $this->field;
        }
        if ($this->op === FilterOperation::IsNull || $this->op === FilterOperation::IsNotNull) {
            $queryBuilder->andWhere("$field $op");
            return;
        }
        $queryBuilder->andWhere("$field $op :$uniqueParam");

        $value = strval($this->value);
        if ($this->op === FilterOperation::Contains) {
            $queryBuilder->setParameter($uniqueParam, "%{$value}%");
        } elseif ($this->op === FilterOperation::BeginsWith) {
            $queryBuilder->setParameter($uniqueParam, "{$value}%");
        } elseif ($this->op === FilterOperation::EndsWith) {
            $queryBuilder->setParameter($uniqueParam, "%{$value}");
        } else {
            $queryBuilder->setParameter($uniqueParam, $this->value);
        }
    }
}