<?php

namespace App\OrdenDetail\Infrastructure;

use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\OrdenDetail\Domain\OrdenDetailRepositoryInterface;

readonly class OrdenDetailRepository implements OrdenDetailRepositoryInterface
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
