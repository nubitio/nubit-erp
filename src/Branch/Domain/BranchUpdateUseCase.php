<?php

namespace App\Branch\Domain;

class BranchUpdateUseCase
{
    private BranchRepositoryInterface $repository;

    public function __construct(BranchRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Branch $branch): Branch
    {
        return $this->repository->update($branch);
    }
}