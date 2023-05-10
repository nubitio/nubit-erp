<?php

namespace App\PriceListDetail\Infrastructure;

use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\PriceListDetail\Domain\PriceListDetailRepositoryInterface;

readonly class PriceListDetailRepository implements PriceListDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}