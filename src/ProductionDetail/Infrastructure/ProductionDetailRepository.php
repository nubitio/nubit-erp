<?php

namespace App\ProductionDetail\Infrastructure;

use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\ProductionDetail\Domain\ProductionDetailRepositoryInterface;

readonly class ProductionDetailRepository implements ProductionDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}
