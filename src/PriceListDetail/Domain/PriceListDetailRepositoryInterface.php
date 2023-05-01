<?php

namespace App\PriceListDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface PriceListDetailRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<PriceListDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}