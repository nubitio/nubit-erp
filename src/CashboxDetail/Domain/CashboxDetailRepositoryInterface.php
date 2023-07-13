<?php

namespace App\CashboxDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface CashboxDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<CashboxDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}
