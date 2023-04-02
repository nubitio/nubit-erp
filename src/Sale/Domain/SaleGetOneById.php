<?php

namespace App\Sale\Domain;

class SaleGetOneById
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, array<int|string, mixed>>
     */
    public function __invoke(string $id): array
    {
        return $this->repository->getOneById($id);
    }
}
