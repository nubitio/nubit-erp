<?php

namespace App\CashboxNew\Domain;

readonly class CashboxUpdateValuesUseCase
{
    public function __construct(
        private CashboxRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $localId, int $userId): void
    {
        $this->repository->updateValues($localId, $userId);
    }
}
