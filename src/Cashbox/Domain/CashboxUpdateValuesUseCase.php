<?php

namespace App\Cashbox\Domain;

class CashboxUpdateValuesUseCase
{
    public function __construct(
        private readonly CashboxRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $localId, int $userId): void
    {
        $this->repository->updateValues($localId, $userId);
    }
}
