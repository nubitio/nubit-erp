<?php

namespace App\CashboxNew\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllCashboxUseCase
{
    public function __construct(
        private CashboxRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Cashbox>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
