<?php

namespace App\CashboxNewDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllCashboxDetailUseCase
{
    public function __construct(
        private CashboxDetailRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<CashboxDetail>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
