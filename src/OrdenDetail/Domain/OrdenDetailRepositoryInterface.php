<?php

namespace App\OrdenDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface OrdenDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<OrdenDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}
