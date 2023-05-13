<?php

namespace App\Core\Infrastructure\DataLoader;

use Doctrine\ORM\QueryBuilder;

interface FilterInterface
{
    public function apply(QueryBuilder $queryBuilder): void;
}
