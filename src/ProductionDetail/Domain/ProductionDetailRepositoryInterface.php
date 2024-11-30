<?php

namespace App\ProductionDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface ProductionDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<ProductionDetail>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;
}
