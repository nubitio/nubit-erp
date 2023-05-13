<?php

namespace App\SaleDetail\Infrastructure;

use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\SaleDetail\Domain\SaleDetailRepositoryInterface;

readonly class SaleDetailRepository implements SaleDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}
