<?php

namespace App\Unit\Domain;

class UnitGetAllUseCase
{
    private UnitRepositoryInterface $repository;

    public function __construct(UnitRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int, mixed>|null $filter
     * @return array<string, mixed>
     */
    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
