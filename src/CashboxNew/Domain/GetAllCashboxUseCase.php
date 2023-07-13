<?php

namespace App\CashboxNew\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllCashboxUseCase
{
    public function __construct(
        private CashboxRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Cashbox>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
