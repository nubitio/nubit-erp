<?php

namespace App\CashboxNewDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllCashboxDetailUseCase
{
    public function __construct(
        private CashboxDetailRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<CashboxDetail>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
