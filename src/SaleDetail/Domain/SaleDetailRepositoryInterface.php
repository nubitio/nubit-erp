<?php

namespace App\SaleDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface SaleDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<SaleDetail>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;
}
