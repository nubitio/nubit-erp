<?php

namespace App\Kardex\Domain;

readonly class GetAllKardexUseCase
{
    public function __construct(
        private KardexRepositoryInterface $repository
    ) {
    }

    /** @return Kardex[] */
    public function __invoke(): array
    {
        return $this->repository->getKardex();
    }
}
