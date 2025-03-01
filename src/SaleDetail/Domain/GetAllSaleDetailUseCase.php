<?php

namespace App\SaleDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

class GetAllSaleDetailUseCase
{
    public function __construct(private readonly SaleDetailRepositoryInterface $repository)
    {
    }

    /**
     * @return PaginatorInterface<SaleDetail>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
