<?php

namespace App\CashboxDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

class CashboxDetailGetAllUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return PaginatorInterface<CashboxDetail>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
