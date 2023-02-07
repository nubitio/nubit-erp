<?php

namespace App\SaleDetail\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\SaleDetail\Domain\SaleDetail;
use App\SaleDetail\Domain\SaleDetailRepositoryInterface;

readonly class SaleDetailRepository implements SaleDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        $paginator = $this->dataLoader->load($options);
        $data = [];
        foreach ($paginator->getIterator() as $saleDetail) {
            $data[] = DataMapper::map($saleDetail, SaleDetail::class);
        }

        return new Paginator($data, $paginator);
    }
}