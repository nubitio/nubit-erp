<?php

namespace App\Sale\Domain;

use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetSalesByCustomerIdUseCase
{
    public function __construct(
        private SaleRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $id): PaginatorInterface
    {
        return $this->repository->getSaleByCustomerId($id);
    }
}
