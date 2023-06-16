<?php

namespace App\ProductionDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface ProductionDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<ProductionDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}
