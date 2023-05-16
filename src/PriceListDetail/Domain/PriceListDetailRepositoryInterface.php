<?php

namespace App\PriceListDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface PriceListDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<PriceListDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}
