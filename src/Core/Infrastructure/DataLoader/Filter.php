<?php

namespace App\Core\Infrastructure\DataLoader;

use Doctrine\ORM\QueryBuilder;

readonly class Filter implements FilterInterface
{
    public function __construct(
        public string          $field,
        public FilterOperation $op,
        public string          $value,
    )
    {
    }

    public function apply(QueryBuilder $queryBuilder)
    {
        $op = FilterUtils::getOperation($this->op);
        $uniqueParam = FilterUtils::getUniqueParam($queryBuilder, $this->field);
        $queryBuilder->andWhere("e.{$this->field} {$op} :{$uniqueParam}");

        if ($this->op === FilterOperation::Contains) {
            $queryBuilder->setParameter($uniqueParam, "%{$this->value}%");
        } elseif ($this->op === FilterOperation::BeginsWith) {
            $queryBuilder->setParameter($uniqueParam, "{$this->value}%");
        } elseif ($this->op === FilterOperation::EndsWith) {
            $queryBuilder->setParameter($uniqueParam, "%{$this->value}");
        } else {
            $queryBuilder->setParameter($uniqueParam, $this->value);
        }
    }
}