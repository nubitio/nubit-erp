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
        $queryBuilder->andWhere("e.{$this->field} {$op} :{$this->field}");

        if ($this->op === FilterOperation::Contains) {
            $queryBuilder->setParameter($this->field, "%{$this->value}%");
        } elseif ($this->op === FilterOperation::BeginsWith) {
            $queryBuilder->setParameter($this->field, "{$this->value}%");
        } elseif ($this->op === FilterOperation::EndsWith) {
            $queryBuilder->setParameter($this->field, "%{$this->value}");
        } else {
            $queryBuilder->setParameter($this->field, $this->value);
        }
    }
}