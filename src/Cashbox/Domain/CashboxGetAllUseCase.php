<?php

namespace App\Cashbox\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

class CashboxGetAllUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return PaginatorInterface<Cashbox>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
