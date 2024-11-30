<?php

namespace App\SaleDetail\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\SaleDetail\Domain\SaleDetailRepositoryInterface;

readonly class SaleDetailRepository implements SaleDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }
}
