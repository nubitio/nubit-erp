<?php

namespace App\Sale\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

class GetAllSaleUseCase
{
    public function __construct(private readonly SaleRepositoryInterface $repository)
    {
    }

    /**
     * @return PaginatorInterface<Sale>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
