<?php

namespace App\PriceListDetail\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\PriceListDetail\Domain\PriceListDetailRepositoryInterface;

readonly class PriceListDetailRepository implements PriceListDetailRepositoryInterface
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
