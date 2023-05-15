<?php

namespace App\Branch\Domain;

class BranchGetOneUseCase
{
    private BranchRepositoryInterface $repository;

    public function __construct(BranchRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Branch
    {
        return $this->repository->getOne($id);
    }
}
