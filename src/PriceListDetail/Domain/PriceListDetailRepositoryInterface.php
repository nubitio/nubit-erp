<?php

namespace App\PriceListDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface PriceListDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<PriceListDetail>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;
}
