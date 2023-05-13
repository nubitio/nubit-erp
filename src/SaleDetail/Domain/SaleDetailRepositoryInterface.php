<?php

namespace App\SaleDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface SaleDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<SaleDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}
