<?php

namespace App\ProductionDetail\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\ProductionDetail\Domain\ProductionDetailRepositoryInterface;

readonly class ProductionDetailRepository implements ProductionDetailRepositoryInterface
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
