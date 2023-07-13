<?php

namespace App\CashboxDetail\Infrastructure;

use App\CashboxDetail\Domain\CashboxDetailRepositoryInterface;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class CashboxDetailRepository implements CashboxDetailRepositoryInterface
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
