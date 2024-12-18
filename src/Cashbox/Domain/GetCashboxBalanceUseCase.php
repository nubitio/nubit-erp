<?php

namespace App\Cashbox\Domain;

final readonly class GetCashboxBalanceUseCase
{
    public function __construct(
        private CashboxRepositoryInterface $cashboxRepository,
    ) {
    }

    public function __invoke(string $cashboxId): float
    {
        return $this->cashboxRepository->getTotalBalance($cashboxId);
    }
}
